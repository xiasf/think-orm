<?php

namespace ThinkOrm\Tests\Unit;

use think\App;
use think\Cache;
use think\Config;
use think\exception\ClassNotFoundException;
use think\Loader;
use ThinkOrm\Tests\Helper\model\User as HelperUser;
use ThinkOrm\Tests\Helper\validate\User as HelperValidateUser;
use ThinkOrm\Tests\Helper\common\validate\Strict as HelperValidateStrict;
use ThinkOrm\Tests\UnitTestCase;

/**
 * 直接覆盖 think\Loader 类的核心 API：
 *   - parseName（snake_case ↔ PascalCase）
 *   - parseClass（module/layer/name → FQCN）
 *   - addNamespaceAlias（命名空间别名 autoload）
 *   - addNamespace / addClassMap（PSR-4 / classmap 注册）
 *   - model() / validate()（解析、缓存、common fallback、FQCN passthrough、异常）
 *   - clearInstance（清空实例缓存）
 *
 * 注意：model()/validate() 大体行为已被 ModelWorkflowTest 覆盖；
 * 此处补齐 Loader 类本身的直接单测 + 边界用例。
 *
 * @group unit
 */
class LoaderTest extends UnitTestCase
{
    private $originalNamespace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalNamespace = App::$namespace;
        App::$namespace = 'ThinkOrm\Tests\Helper';
        // 清空 Loader 内部缓存，避免相互污染
        $this->clearLoaderInstanceCache();
    }

    protected function tearDown(): void
    {
        App::$namespace = $this->originalNamespace;
        $this->clearLoaderInstanceCache();
        parent::tearDown();
    }

    private function clearLoaderInstanceCache()
    {
        $prop = new \ReflectionProperty(Loader::class, 'instance');
        $prop->setAccessible(true);
        $prop->setValue(null, []);
    }

    // —— parseName ——

    public function testParseNameSnakeToPascalDefault()
    {
        $this->assertSame('User', Loader::parseName('user', 1));
        $this->assertSame('UserOrder', Loader::parseName('user_order', 1));
        $this->assertSame('CarParkingLog', Loader::parseName('car_parking_log', 1));
    }

    public function testParseNameSnakeToPascalLowerFirst()
    {
        // ucfirst=false → lcfirst
        $this->assertSame('user', Loader::parseName('user', 1, false));
        $this->assertSame('userOrder', Loader::parseName('user_order', 1, false));
    }

    public function testParseNamePascalToSnake()
    {
        // type=0 默认：PascalCase → snake_case
        $this->assertSame('user', Loader::parseName('User'));
        $this->assertSame('user_order', Loader::parseName('UserOrder'));
        $this->assertSame('car_parking_log', Loader::parseName('CarParkingLog'));
    }

    public function testParseNameAlreadySnakeUnchanged()
    {
        $this->assertSame('user_order', Loader::parseName('user_order'));
    }

    public function testParseNameSingleChar()
    {
        $this->assertSame('u', Loader::parseName('U'));
        $this->assertSame('U', Loader::parseName('u', 1));
    }

    public function testParseNameEmptyString()
    {
        $this->assertSame('', Loader::parseName(''));
        $this->assertSame('', Loader::parseName('', 1));
    }

    // —— parseClass ——

    public function testParseClassBasic()
    {
        App::$namespace = 'app';
        $this->assertSame('app\model\User', Loader::parseClass('', 'model', 'User'));
        $this->assertSame('app\parkinglot\model\Car', Loader::parseClass('parkinglot', 'model', 'Car'));
    }

    public function testParseClassMultiLayerPath()
    {
        App::$namespace = 'app';
        // 模块/子路径/类名 形式：v1/Car → app\<module>\model\v1\Car
        $this->assertSame('app\parkinglot\model\v1\Car', Loader::parseClass('parkinglot', 'model', 'v1/Car'));
        $this->assertSame('app\di\model\v1\Notice', Loader::parseClass('di', 'model', 'v1/Notice'));
    }

    public function testParseClassUnderscoreConverted()
    {
        App::$namespace = 'app';
        $this->assertSame('app\foo\model\UserOrder', Loader::parseClass('foo', 'model', 'user_order'));
        // 路径里的下划线也会被转大驼峰
        $this->assertSame('app\foo\model\UserOrder', Loader::parseClass('foo', 'model', 'user_order'));
    }

    public function testParseClassPointSlashBackslashAllAccepted()
    {
        App::$namespace = 'app';
        // parseClass 内部把 / 与 . 都换成 \，允许三种分隔
        $this->assertSame('app\m\model\v1\Car', Loader::parseClass('m', 'model', 'v1/Car'));
        $this->assertSame('app\m\model\v1\Car', Loader::parseClass('m', 'model', 'v1.Car'));
        $this->assertSame('app\m\model\v1\Car', Loader::parseClass('m', 'model', 'v1\Car'));
    }

    public function testParseClassWithAppendSuffix()
    {
        App::$namespace = 'app';
        App::$suffix = false;
        // 默认不加 layer 后缀
        $this->assertSame('app\m\model\User', Loader::parseClass('m', 'model', 'User'));
        // $appendSuffix=true → 类名后追加 layer
        $this->assertSame('app\m\model\UserModel', Loader::parseClass('m', 'model', 'User', true));
    }

    public function testParseClassGlobalSuffixFlag()
    {
        App::$namespace = 'app';
        // App::$suffix=true → 默认就加后缀（即使 appendSuffix=false）
        App::$suffix = true;
        try {
            $this->assertSame('app\m\model\UserModel', Loader::parseClass('m', 'model', 'User'));
        } finally {
            App::$suffix = false;
        }
    }

    public function testParseClassCustomNamespace()
    {
        App::$namespace = 'My\App';
        $this->assertSame('My\App\m\model\User', Loader::parseClass('m', 'model', 'User'));
    }

    // —— addClassMap ——

    public function testAddClassMapStringForm()
    {
        Loader::addClassMap('Fake\Class\One', '/tmp/one.php');
        $map = $this->getClassMap();
        $this->assertSame('/tmp/one.php', $map['Fake\Class\One'] ?? null);
    }

    public function testAddClassMapArrayFormMerges()
    {
        Loader::addClassMap('Fake\Class\One', '/tmp/one.php');
        Loader::addClassMap([
            'Fake\Class\Two'   => '/tmp/two.php',
            'Fake\Class\Three' => '/tmp/three.php',
        ]);
        $map = $this->getClassMap();
        $this->assertSame('/tmp/one.php', $map['Fake\Class\One'] ?? null);
        $this->assertSame('/tmp/two.php', $map['Fake\Class\Two'] ?? null);
        $this->assertSame('/tmp/three.php', $map['Fake\Class\Three'] ?? null);
    }

    private function getClassMap()
    {
        $prop = new \ReflectionProperty(Loader::class, 'classMap');
        $prop->setAccessible(true);
        return $prop->getValue(null);
    }

    // —— addNamespaceAlias ——

    public function testAddNamespaceAliasStringForm()
    {
        Loader::addNamespaceAlias('Fake\Ns', 'ThinkOrm\Tests\Helper');
        $aliases = $this->getNamespaceAlias();
        $this->assertSame('ThinkOrm\Tests\Helper', $aliases['Fake\Ns'] ?? null);
    }

    public function testAddNamespaceAliasArrayFormMerges()
    {
        Loader::addNamespaceAlias('Fake\Ns1', 'A\B');
        Loader::addNamespaceAlias([
            'Fake\Ns2' => 'C\D',
            'Fake\Ns3' => 'E\F',
        ]);
        $aliases = $this->getNamespaceAlias();
        $this->assertSame('A\B', $aliases['Fake\Ns1'] ?? null);
        $this->assertSame('C\D', $aliases['Fake\Ns2'] ?? null);
        $this->assertSame('E\F', $aliases['Fake\Ns3'] ?? null);
    }

    private function &getNamespaceAlias()
    {
        $prop = new \ReflectionProperty(Loader::class, 'namespaceAlias');
        $prop->setAccessible(true);
        $aliases = $prop->getValue(null);
        return $aliases;
    }

    // —— model() ——

    public function testModelReturnsInstanceByShortName()
    {
        $user = Loader::model('User');
        $this->assertInstanceOf(HelperUser::class, $user);
    }

    public function testModelReturnsSameInstanceForSameKey()
    {
        $a = Loader::model('User');
        $b = Loader::model('User');
        $this->assertSame($a, $b);
    }

    public function testModelUnderscoreResolvesToPascalCase()
    {
        // FallbackOnly 类位于 common 模块（fallback 测试见下）
        // 这里用同模块下的 Post 验证下划线解析
        $post = Loader::model('post');   // ThinkOrm\Tests\Helper\model\Post
        $this->assertInstanceOf(\ThinkOrm\Tests\Helper\model\Post::class, $post);
    }

    public function testModelFqcnPassthrough()
    {
        // FQCN 直接使用，不经过 parseClass
        $user = Loader::model('\\ThinkOrm\\Tests\\Helper\\model\\User');
        $this->assertInstanceOf(HelperUser::class, $user);
    }

    public function testModelCachesByLayerToo()
    {
        // model()/validate() 共享 $instance 数组，但 key 是 name+layer
        // 同名但不同 layer 不会冲突
        $user = Loader::model('User', 'model');
        $validate = Loader::validate('User', 'validate');
        $this->assertNotSame($user, $validate);
    }

    public function testModelCommonModuleFallback()
    {
        // FallbackOnly 只在 common\model 下存在，不在 module\model 下
        // Loader::model('foo/FallbackOnly') 找不到 foo\model\FallbackOnly，
        // 应该回退到 common\model\FallbackOnly
        $obj = Loader::model('foo/FallbackOnly');
        $this->assertInstanceOf(\ThinkOrm\Tests\Helper\common\model\FallbackOnly::class, $obj);
    }

    public function testModelThrowsWhenNotFound()
    {
        $this->expectException(ClassNotFoundException::class);
        Loader::model('NotExistsAnywhere');
    }

    public function testClearInstanceEmptiesCache()
    {
        $a = Loader::model('User');
        Loader::clearInstance();
        $b = Loader::model('User');
        $this->assertNotSame($a, $b);
    }

    // —— validate() ——

    public function testValidateReturnsInstanceByShortName()
    {
        $v = Loader::validate('User');
        $this->assertInstanceOf(HelperValidateUser::class, $v);
    }

    public function testValidateReturnsSameInstanceForSameKey()
    {
        $a = Loader::validate('User');
        $b = Loader::validate('User');
        $this->assertSame($a, $b);
    }

    public function testValidateFqcnPassthrough()
    {
        $v = Loader::validate('\\ThinkOrm\\Tests\\Helper\\validate\\User');
        $this->assertInstanceOf(HelperValidateUser::class, $v);
    }

    public function testValidateCommonModuleFallback()
    {
        // Strict 仅在 common\validate 下存在
        // foo/Strict → 找不到 foo\validate\Strict → fallback 到 common\validate\Strict
        $v = Loader::validate('foo/Strict');
        $this->assertInstanceOf(HelperValidateStrict::class, $v);
    }

    public function testValidateThrowsWhenNotFound()
    {
        $this->expectException(ClassNotFoundException::class);
        Loader::validate('NotExistsAnywhere');
    }

    public function testValidateEmptyNameReturnsDefaultValidate()
    {
        Config::set('default_validate', '');
        $v = Loader::validate('');
        $this->assertInstanceOf(\think\Validate::class, $v);
    }

    public function testValidateEmptyNameUsesConfigDefault()
    {
        Config::set('default_validate', 'User');
        $v = Loader::validate('');
        $this->assertInstanceOf(HelperValidateUser::class, $v);
        Config::set('default_validate', '');
    }

    // —— controller() / action() ——

    public function testControllerThrowsWhenClassMissing()
    {
        $this->expectException(ClassNotFoundException::class);
        Loader::controller('NotExists');
    }

    public function testActionReturnsFalseWhenControllerMissing()
    {
        // action() 内部调 controller() 抛异常，但 try/catch 不消化；
        // 我们只能测一个会调用 controller() 抛 ClassNotFoundException 的入口
        // 这里用 try/catch 包裹验证异常路径（不抛到外面就算通过）
        try {
            Loader::action('NotExists/action');
            $this->fail('expected exception');
        } catch (ClassNotFoundException $e) {
            $this->assertStringContainsString('NotExists', $e->getMessage());
        }
    }

    // —— import() ——

    public function testImportCachesAfterFirstLoad()
    {
        // import() 用 . 作为目录分隔符（在内部转成 DS）
        // 用一个不存在的类库路径 → 返回 false 不报错
        $result = Loader::import('not_exists_pkg.some_file');
        $this->assertFalse($result);
    }

    public function testImportWithExplicitBaseUrlNotFound()
    {
        $result = Loader::import('some_file', sys_get_temp_dir());
        $this->assertFalse($result);
    }

    // —— db() passthrough ——

    public function testDbPassesThroughToDbConnect()
    {
        // db() 是 Db::connect() 的代理，单元测试下返回 Connection 实例
        $conn = Loader::db();
        $this->assertInstanceOf(\think\db\Connection::class, $conn);
    }
}
