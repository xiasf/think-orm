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

    /**
     * yf/TP 5.0.24 标准 database.php 全配置项都应在 Orm::boot 后存在
     * 这是把 yf 项目切到本包时最常踩的雷——某项缺了用户传不进来
     */
    public function testDatabaseDefaultsIncludeAllYfKeys()
    {
        $db = Config::get('database');
        $this->assertIsArray($db);

        // 与 yf application/database.php 一一对应
        $requiredKeys = [
            'type', 'hostname', 'database', 'username', 'password',
            'hostport', 'dsn', 'params', 'charset', 'prefix',
            'debug', 'deploy', 'rw_separate', 'master_num', 'slave_no',
            'read_master', 'fields_strict', 'resultset_type',
            'auto_timestamp', 'datetime_format', 'sql_explain',
        ];
        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey($key, $db, "缺少 yf 标准配置项: database.{$key}");
        }
    }

    public function testDatabaseParamsDefaultIsEmptyArray()
    {
        // params 是 PDO 构造参数，必须默认 [] 而不是 null（Connection 用 + 合并）
        $this->assertIsArray(Config::get('database.params'));
        $this->assertSame([], Config::get('database.params'));
    }

    public function testDatabaseSocketAndReadMasterExist()
    {
        // 这两项原本运行时支持但 defaults 缺失，现已补齐
        $this->assertSame('', Config::get('database.socket'));
        $this->assertFalse(Config::get('database.read_master'));
    }

    public function testOrmBootPreservesUserPassedParams()
    {
        // 用户传的 params 不能被 defaults 覆盖
        Orm::boot([
            'database' => [
                'params' => [\PDO::ATTR_PERSISTENT => true],
                'socket' => '/tmp/mysql.sock',
                'read_master' => true,
            ],
        ]);
        $this->assertTrue(Config::get('database.params')[\PDO::ATTR_PERSISTENT] ?? false);
        $this->assertSame('/tmp/mysql.sock', Config::get('database.socket'));
        $this->assertTrue(Config::get('database.read_master'));

        // 还原（避免污染后续测试）
        Orm::boot([
            'database' => ['params' => [], 'socket' => '', 'read_master' => false],
        ]);
    }

    /**
     * boot() 第二次调用：配置应合并
     *  - 未注入 PSR-3 logger 时，log.file 会被更新（这是单例模式正常行为）
     *  - **注入过 PSR-3 logger 后**，log.file 会被静默忽略 —— 这才是用户反馈的真实坑
     *    GitHub #1：测试中切 log 配置失败
     */
    public function testBootRepeatAfterPsr3LoggerIgnoresLogFile()
    {
        $tmpA = tempnam(sys_get_temp_dir(), 'logA') . '.log';
        $tmpB = tempnam(sys_get_temp_dir(), 'logB') . '.log';

        // 1) 首次 boot：log.file = tmpA，未注入 PSR-3 logger → 文件日志写入
        Orm::boot(['log' => ['file' => $tmpA]]);
        \think\Log::record('first', 'sql');
        $this->assertFileExists($tmpA);

        // 2) 注入 PSR-3 logger
        $fakeLogger = new class {
            public $logs = [];
            public function log($level, $msg, array $ctx = []) { $this->logs[] = "[$level] $msg"; }
        };
        \think\Log::setLogger($fakeLogger);

        // 3) 第二次 boot：log.file = tmpB
        // —— Log::getLogger() !== null，boot 内部 if 跳过 setLogFile
        // 即使没跳过，record 也会走 PSR-3 logger 然后 return，文件永远不写
        Orm::boot(['log' => ['file' => $tmpB]]);
        \think\Log::record('second', 'sql');

        $this->assertFileDoesNotExist($tmpB, '注入 PSR-3 logger 后，文件日志永远不写');
        $this->assertCount(1, $fakeLogger->logs, '应走 PSR-3 logger');

        // 4) refreshLog 调用 —— 注入 PSR-3 logger 后，refreshLog 设置路径但 record 仍走 logger
        Orm::refreshLog($tmpB);
        \think\Log::record('third', 'sql');
        $this->assertFileDoesNotExist($tmpB, 'PSR-3 logger 已注入时，refreshLog 也无法让文件被写');
        $this->assertCount(2, $fakeLogger->logs, '仍走 PSR-3 logger');

        // 5) 想"切回"文件日志：必须先 setLogger(null) 移除 PSR-3 logger
        \think\Log::setLogger(null);
        \think\Log::record('fourth', 'sql');
        $this->assertFileExists($tmpB, 'setLogger(null) 后 refreshLog 设的路径才生效');

        // 清理
        Orm::refreshLog(null);
        @unlink($tmpA);
        @unlink($tmpB);
    }

    /**
     * refreshLog(null) 关闭文件日志
     */
    public function testRefreshLogNullDisablesFileLogging()
    {
        $tmp = tempnam(sys_get_temp_dir(), 'logC') . '.log';
        Orm::refreshLog($tmp);
        \think\Log::record('x', 'sql');
        $this->assertFileExists($tmp);

        Orm::refreshLog(null);
        $size = filesize($tmp);
        \think\Log::record('y', 'sql');
        clearstatcache();
        $this->assertSame($size, filesize($tmp), '关闭后不应再写入');

        @unlink($tmp);
    }
}
