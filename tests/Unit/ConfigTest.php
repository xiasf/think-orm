<?php

namespace ThinkOrm\Tests\Unit;

use think\Config;
use ThinkOrm\Orm;
use ThinkOrm\Tests\UnitTestCase;

/**
 * @group unit
 */
class ConfigTest extends UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::reset();
        // 重新 boot 一次：parent::setUp 已经 Orm::boot 过一次，但 reset() 把 config 清了
        Orm::boot([
            'database' => ['type' => 'mysql'],
            'paginate' => ['list_rows' => 15],
        ]);
    }

    public function testSetAndGetSimple()
    {
        Config::set('app_name', 'think-orm');
        $this->assertSame('think-orm', Config::get('app_name'));
    }

    public function testHas()
    {
        Config::set('exists_key', 1);
        $this->assertTrue(Config::has('exists_key'));
        $this->assertFalse(Config::has('missing_key'));
    }

    public function testGetWithDot()
    {
        Config::set(['db' => ['host' => 'h1', 'port' => 3306]]);
        $this->assertSame('h1', Config::get('db.host'));
        $this->assertSame(3306, Config::get('db.port'));
        $this->assertNull(Config::get('db.missing'));
        $this->assertNull(Config::get('missing.key'));  // 关键：模块动态加载分支已移除
    }

    public function testGetAll()
    {
        Config::set(['a' => 1]);
        $all = Config::get();
        $this->assertIsArray($all);
        $this->assertArrayHasKey('a', $all);
    }

    public function testSetArrayWithName()
    {
        Config::set(['key1' => 'val1', 'key2' => 'val2']);
        $this->assertSame('val1', Config::get('key1'));
        $this->assertSame('val2', Config::get('key2'));
    }

    public function testSetArrayWithValueAsSubKey()
    {
        // Config::set($name=array, $value='group', $range='_sys_') 会把数组写入 self::$config[range][group]
        Config::set(['sub1' => 'a', 'sub2' => 'b'], 'group1');
        $this->assertSame('a', Config::get('group1.sub1'));
        $this->assertSame('b', Config::get('group1.sub2'));

        // 二次 set 同 group 会 merge
        Config::set(['sub3' => 'c'], 'group1');
        $this->assertSame('a', Config::get('group1.sub1'));
        $this->assertSame('c', Config::get('group1.sub3'));
    }

    public function testLoadPhpFile()
    {
        $tmp = tempnam(sys_get_temp_dir(), 'cfg') . '.php';
        file_put_contents($tmp, '<?php return ["loaded_key" => "loaded_value"];');
        Config::load($tmp);
        $this->assertSame('loaded_value', Config::get('loaded_key'));
        @unlink($tmp);
    }

    public function testRange()
    {
        Config::range('custom');
        Config::set('in_custom', 'yes');
        $this->assertSame('yes', Config::get('in_custom'));
        Config::reset();
        Config::range('_sys_');
    }

    public function testParseYamlIsNotInstalledGracefully()
    {
        // parse('php_string') 走 \think\config\driver\Php，未实现就跳过
        // 这里只验证 method 存在可调用
        $this->assertTrue(method_exists(Config::class, 'parse'));
    }
}
