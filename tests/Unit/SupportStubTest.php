<?php

namespace ThinkOrm\Tests\Unit;

use ThinkOrm\Tests\UnitTestCase;
use think\App;
use think\Cache;
use think\Config;
use think\Debug;
use think\Lang;
use think\Log;
use think\Request;
use think\Session;

/**
 * @group unit
 */
class SupportStubTest extends UnitTestCase
{
    public function testAppDefaults()
    {
        $this->assertFalse(App::$debug);
        $this->assertFalse(App::$suffix);
        $this->assertSame('app', App::$namespace);
        $this->assertSame('', App::$modulePath);
    }

    public function testAppInvokeClass()
    {
        $obj = App::invokeClass(\stdClass::class);
        $this->assertInstanceOf(\stdClass::class, $obj);
    }

    public function testAppInvokeMethodStatic()
    {
        $result = App::invokeMethod('\ThinkOrm\Tests\Fixture\InvokeTarget::hello', ['World']);
        $this->assertSame('hello World', $result);
    }

    public function testAppInvokeMethodInstance()
    {
        $target = new \ThinkOrm\Tests\Fixture\InvokeTarget();
        $result = App::invokeMethod([$target, 'hello'], ['Universe']);
        $this->assertSame('hello Universe', $result);
    }

    public function testRequestDefaults()
    {
        Request::reset();
        $r = Request::instance();
        $this->assertSame('', $r->module());
        $this->assertSame('', $r->controller());
        $this->assertSame('/', $r->baseUrl());
        $this->assertSame('GET', $r->method());
        $this->assertNull($r->param('foo'));
        $this->assertSame('default', $r->param('foo', 'default'));
    }

    public function testRequestSetInstance()
    {
        $mock = new RequestMock();
        Request::setInstance($mock);
        $this->assertSame('POST', Request::instance()->method());
        $this->assertSame('/admin', Request::instance()->baseUrl());
        Request::reset();
    }

    public function testLangIsNoOp()
    {
        $this->assertSame('hello', Lang::get('hello'));
        $this->assertFalse(Lang::has('hello'));
    }

    public function testSessionIsNoOp()
    {
        $this->assertFalse(Session::has('user'));
        $this->assertNull(Session::get('user'));
        Session::delete('user');
        $this->assertTrue(true);
    }

    public function testDebugRemarkAndGetRange()
    {
        Debug::remark('start', 'time');
        usleep(1000);
        Debug::remark('end', 'time');
        $range = (float) Debug::getRangeTime('start', 'end', 8);
        $this->assertGreaterThanOrEqual(0, $range);

        // 未设置标记点
        $this->assertSame(0, Debug::getRangeTime('not_set_a', 'not_set_b'));
        $this->assertSame(0, Debug::getRangeMem('not_set_a', 'not_set_b'));
    }

    public function testDebugMemRemark()
    {
        Debug::remark('m1', 'mem');
        $arr = str_repeat('x', 1024);
        Debug::remark('m2', 'mem');
        $delta = Debug::getRangeMem('m1', 'm2');
        $this->assertGreaterThanOrEqual(0, $delta);
    }

    public function testCacheNoOpGetReturnsDefault()
    {
        Cache::setInstance(null);
        $this->assertFalse(Cache::get('foo'));
        $this->assertSame('bar', Cache::get('foo', 'bar'));
        $this->assertFalse(Cache::has('foo'));
        $this->assertFalse(Cache::set('foo', 'value'));
        $this->assertFalse(Cache::rm('foo'));
        $this->assertFalse(Cache::delete('foo'));
        $this->assertFalse(Cache::clear());
    }

    public function testCacheTagChainNoOp()
    {
        Cache::setInstance(null);
        $tagged = Cache::tag('mytag');
        $this->assertNotNull($tagged);
        // 链式调用 set 应不报错
        $tagged->set('foo', 'bar');
        $tagged->clear();
        $this->assertTrue(true);
    }

    public function testCacheCallStaticFallback()
    {
        Cache::setInstance(null);
        // __callStatic 兜底：inc('foo', 5) 应返回 step=5
        $result = Cache::inc('foo', 5);
        $this->assertSame(5, $result);
        // 只有 name 参数：返回 name
        $this->assertSame('only_name', Cache::foo('only_name'));
    }

    public function testCacheInjectableHandler()
    {
        $captured = [];
        $handler = new class($captured) {
            private $store = [];
            public $calls;
            public function __construct(&$calls) { $this->calls = &$calls; }
            public function get($name, $default = false) { $this->calls[] = "get:$name"; return $this->store[$name] ?? $default; }
            public function set($name, $value, $expire = 0) { $this->calls[] = "set:$name=$value"; $this->store[$name] = $value; return true; }
            public function has($name) { $this->calls[] = "has:$name"; return isset($this->store[$name]); }
            public function delete($name) { $this->calls[] = "delete:$name"; unset($this->store[$name]); return true; }
            public function clear() { $this->calls[] = "clear"; $this->store = []; return true; }
            public function tag($name) { $this->calls[] = "tag:$name"; return $this; }
            public function inc($name, $step = 1) { $this->calls[] = "inc:$name+$step"; $this->store[$name] = ($this->store[$name] ?? 0) + $step; return $this->store[$name]; }
        };
        Cache::setInstance($handler);
        $this->assertTrue(Cache::set('a', 1));
        $this->assertTrue(Cache::has('a'));
        $this->assertSame(1, Cache::get('a'));
        $this->assertSame(6, Cache::inc('a', 5));
        Cache::tag('g')->set('b', 2);
        Cache::clear();
        Cache::setInstance(null);
        $this->assertContains('set:a=1', $handler->calls);
        $this->assertContains('has:a', $handler->calls);
        $this->assertContains('inc:a+5', $handler->calls);
        $this->assertContains('tag:g', $handler->calls);
        $this->assertContains('clear', $handler->calls);
    }

    public function testLogNoOpDefault()
    {
        Log::clear();
        Log::setLogger(null);
        Log::record('hello', 'info');
        $logs = Log::getLog();
        $this->assertArrayHasKey('info', $logs);
        $this->assertContains('hello', $logs['info']);
        $this->assertContains('hello', Log::getLog('info'));
        $this->assertTrue(Log::save());
    }

    public function testLogInjectablePsr3()
    {
        $received = [];
        $logger = new class($received) {
            public $messages;
            public function __construct(&$m) { $this->messages = &$m; }
            public function log($level, $message, array $context = []) { $this->messages[] = "$level|$message"; }
        };
        Log::clear();
        Log::setLogger($logger);
        Log::record('SQL foo', 'sql');
        Log::record('something broke', 'error');
        Log::setLogger(null);
        $this->assertContains('debug|SQL foo', $logger->messages);
        $this->assertContains('error|something broke', $logger->messages);
    }

    public function testLogFileWriting()
    {
        $tmp = sys_get_temp_dir() . DS . 'orm_test_' . uniqid() . '.log';
        @unlink($tmp);

        Log::clear();
        Log::setLogger(null);
        Log::setLogFile($tmp);
        Log::record('SELECT 1', 'sql');
        Log::record('boom', 'error');
        Log::setLogFile(null);

        $this->assertFileExists($tmp);
        $content = file_get_contents($tmp);
        $this->assertStringContainsString('[sql] SELECT 1', $content);
        $this->assertStringContainsString('[error] boom', $content);
        @unlink($tmp);
    }

    public function testLogFileDisabledByDefault()
    {
        Log::clear();
        Log::setLogger(null);
        Log::setLogFile(null);
        Log::record('silent', 'sql');
        // 仅在内存缓冲，不写盘（没有 file path）
        $this->assertContains('silent', Log::getLog('sql'));
    }

    public function testPsr3LoggerBeatsFileLog()
    {
        $tmp = sys_get_temp_dir() . DS . 'orm_test_' . uniqid() . '.log';
        @unlink($tmp);

        $received = [];
        $logger = new class($received) {
            public $messages;
            public function __construct(&$m) { $this->messages = &$m; }
            public function log($level, $message, array $context = []) { $this->messages[] = "$level|$message"; }
        };

        Log::clear();
        Log::setLogger($logger);
        Log::setLogFile($tmp);
        Log::record('hello', 'sql');
        Log::setLogger(null);
        Log::setLogFile(null);

        // PSR-3 logger 收到
        $this->assertContains('debug|hello', $logger->messages);
        // 文件未写（PSR-3 优先级最高，文件日志被跳过）
        $this->assertFileDoesNotExist($tmp);
    }

    public function testConfigGetSetHas()
    {
        Config::set('foo', 'bar');
        $this->assertSame('bar', Config::get('foo'));
        $this->assertTrue(Config::has('foo'));

        Config::set(['nested' => ['a' => 1, 'b' => 2]]);
        $this->assertSame(1, Config::get('nested.a'));
        $this->assertSame(2, Config::get('nested.b'));
        $this->assertFalse(Config::has('nested.c'));
        $this->assertNull(Config::get('nested.c'));
    }

    public function testConfigLoadNonExistentFileReturnsEmpty()
    {
        $result = Config::load(__DIR__ . '/non_existent.php');
        $this->assertIsArray($result);
    }
}

class RequestMock extends Request
{
    public function method() { return 'POST'; }
    public function baseUrl() { return '/admin'; }
}
