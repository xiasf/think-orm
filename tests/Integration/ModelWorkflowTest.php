<?php

namespace ThinkOrm\Tests\Integration;

use think\App;
use think\Config;
use think\Loader;
use think\Db;
use ThinkOrm\Tests\Helper\model\User as HelperUser;
use ThinkOrm\Tests\Helper\validate\User as HelperValidate;
use ThinkOrm\Tests\IntegrationTestCase;

/**
 * @group integration
 *
 * 模拟 yf 项目的日常用法：
 *   $user = model('User');          // 返回 app\model\User 实例
 *   $v   = validate('User');        // 返回 app\validate\User 实例
 *
 * 这里通过临时改 App::$namespace 让 helper 解析到 tests\Helper\ 命名空间
 */
class ModelWorkflowTest extends IntegrationTestCase
{
    private $originalNamespace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalNamespace = App::$namespace;
        App::$namespace = 'ThinkOrm\Tests\Helper';
        HelperUser::$eventLog = [];
    }

    protected function tearDown(): void
    {
        App::$namespace = $this->originalNamespace;
        // 清除 Loader 实例缓存，避免下次拿到旧命名空间下的对象
        $prop = new \ReflectionProperty(Loader::class, 'instance');
        $prop->setAccessible(true);
        $prop->setValue(null, []);
        parent::tearDown();
    }

    public function testModelHelperReturnsInstance()
    {
        $user = model('User');
        $this->assertInstanceOf(HelperUser::class, $user);
    }

    public function testModelHelperCachesInstance()
    {
        $a = model('User');
        $b = model('User');
        $this->assertSame($a, $b);
    }

    public function testModelHelperWithCrud()
    {
        $u = new HelperUser();
        $u->data(['name' => 'alice', 'email' => 'a@x', 'age' => 20]);
        $u->save();
        $this->assertNotEmpty($u->id);
        $this->assertSame('alice', Db::name('users')->where('id', $u->id)->value('name'));
    }

    public function testValidateHelperReturnsValidator()
    {
        $v = validate('User');
        $this->assertInstanceOf(HelperValidate::class, $v);
    }

    public function testValidateHelperCheck()
    {
        $v = validate('User');
        $this->assertTrue($v->check(['name' => 'a', 'email' => 'a@b.com', 'age' => 18]));
    }

    public function testValidateHelperFailure()
    {
        $v = validate('User');
        $this->assertFalse($v->check(['name' => '', 'email' => 'bad', 'age' => -1]));
        $errors = $v->getError();
        $this->assertNotEmpty($errors);
    }

    public function testValidateCustomMessage()
    {
        $v = validate('User');
        $v->check(['name' => '']);
        $err = $v->getError();
        // 名称字段必填，message 是中文
        $this->assertStringContainsString('名字必须填', is_array($err) ? implode(';', $err) : $err);
    }

    public function testValidateSceneCreate()
    {
        $v = validate('User');
        // create 场景：name/email/age 都需要
        $this->assertFalse($v->scene('create')->check(['name' => 'a']));
        // login 场景：只需 email
        $this->assertTrue($v->scene('login')->check(['email' => 'a@b.com']));
    }

    public function testModelHiddenVisibleAppend()
    {
        $id = $this->seedUser(['name' => 'alice', 'email' => 'a@x', 'age' => 20]);
        $u = HelperUser::get($id);

        $arr = $u->toArray();
        $this->assertArrayNotHasKey('email', $arr);  // hidden
        $this->assertArrayHasKey('upper_name', $arr);  // append
        $this->assertSame('ALICE', $arr['upper_name']);
    }

    public function testModelScopeActive()
    {
        $this->seedUser(['name' => 'a', 'is_active' => 1]);
        $this->seedUser(['name' => 'b', 'is_active' => 0]);
        $this->seedUser(['name' => 'c', 'is_active' => 1]);

        $rows = HelperUser::active()->select();
        $this->assertCount(2, $rows);
    }

    public function testModelEventsFire()
    {
        HelperUser::event('before_write', [HelperUser::class, 'onBeforeWrite']);
        HelperUser::event('after_insert', [HelperUser::class, 'onAfterInsert']);

        $u = new HelperUser();
        $u->data(['name' => 'eve', 'email' => 'e@x', 'age' => 30]);
        $u->save();
        $this->assertContains('before_write:eve', HelperUser::$eventLog);
        $this->assertContains('after_insert:eve', HelperUser::$eventLog);
    }

    public function testReadonlyFieldIgnoredOnUpdate()
    {
        $id = $this->seedUser(['name' => 'orig', 'email' => 'o@x', 'age' => 1]);
        $u = HelperUser::get($id);
        $u->name = 'changed';
        $u->age = 99;
        $u->save();
        // name 是 readonly，应保持原值
        $this->assertSame('orig', Db::name('users')->where('id', $id)->value('name'));
        $this->assertSame('99', (string) Db::name('users')->where('id', $id)->value('age'));
    }

    public function testValidateHelperAcceptsFullClassName()
    {
        $v = validate('\\ThinkOrm\\Tests\\Helper\\validate\\User');
        $this->assertInstanceOf(HelperValidate::class, $v);
    }

    public function testModelHelperAcceptsFullClassName()
    {
        $m = model('\\ThinkOrm\\Tests\\Helper\\model\\User');
        $this->assertInstanceOf(HelperUser::class, $m);
    }
}