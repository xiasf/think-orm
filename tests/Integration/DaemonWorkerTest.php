<?php

namespace ThinkOrm\Tests\Integration;

use example\daemon\BaseWorker;
use think\Db;
use think\Model;
use ThinkOrm\Tests\IntegrationTestCase;
use PDO;
use PDOException;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;
use Throwable;

/**
 * @group integration
 *
 * 守护进程 BaseWorker 关键 API 测试
 *
 * 覆盖：
 *   - initDb / heartbeat 探活
 *   - checkDbBreak 关键词识别（DB 断线 vs 业务异常）
 *   - reconnectDb 双缓存清空（Db::$instance + Model::$links）
 *   - cli vs phpfpm 持久化策略
 *   - 完整生命周期（有限迭代模式）
 *   - 错误恢复（模拟断线 → 自动重连）
 */
class DaemonWorkerTest extends IntegrationTestCase
{
    /** @var BaseWorker|null 测试中实例化的 worker（onWorkerStart/Stop 不应有副作用） */
    private $worker;

    protected function setUp(): void
    {
        parent::setUp();
        // 用匿名子类（onTick 留空，测试期间不进入主循环）
        $this->worker = new class extends BaseWorker {
            protected function onTick(): void {}
        };
        $this->worker->setMaxIterations(0); // 防止误触发主循环
    }

    // --------------------------------------------------------------
    // initDb / heartbeat
    // --------------------------------------------------------------

    public function testInitDbConnectsAndSetsLastHeartbeat()
    {
        // initDb 是 protected，反射调用
        $m = new ReflectionMethod($this->worker, 'initDb');
        $m->setAccessible(true);
        $m->invoke($this->worker);

        $this->assertGreaterThan(0.0, $this->getPrivate($this->worker, 'lastHeartbeat'));
    }

    public function testHeartbeatExecutesSelectOneWithoutError()
    {
        $m = new ReflectionMethod($this->worker, 'heartbeat');
        $m->setAccessible(true);
        $m->invoke($this->worker); // 不抛即通过

        $this->assertTrue(true);
    }

    // --------------------------------------------------------------
    // checkDbBreak 关键词识别
    // --------------------------------------------------------------

    public function testCheckDbBreakDetectsMysqlGoneAway()
    {
        $e = new PDOException('SQLSTATE[HY000]: General error: 2006 MySQL server has gone away');
        $this->assertTrue($this->callCheckDbBreak($e));
    }

    public function testCheckDbBreakDetectsLostConnectionDuringQuery()
    {
        $e = new PDOException('SQLSTATE[HY000]: Lost connection to MySQL server during query');
        $this->assertTrue($this->callCheckDbBreak($e));
    }

    public function testCheckDbBreakDetectsBrokenPipe()
    {
        $e = new RuntimeException('fwrite(): Broken pipe');
        $this->assertTrue($this->callCheckDbBreak($e));
    }

    public function testCheckDbBreakIgnoresBusinessLogicError()
    {
        $e = new RuntimeException('invalid payload format');
        $this->assertFalse($this->callCheckDbBreak($e));
    }

    public function testCheckDbBreakIgnoresValidateError()
    {
        $e = new RuntimeException('字段 name 必须');
        $this->assertFalse($this->callCheckDbBreak($e));
    }

    // --------------------------------------------------------------
    // reconnectDb 双缓存清空
    // --------------------------------------------------------------

    public function testReconnectDbClearsModelLinksCache()
    {
        // 先让 Model::$links 有内容：通过 model 类首次查询触发 db() 缓存
        $before = $this->getModelLinks();
        $this->assertEmpty($before);

        // 让 Model 内部静态调用 db() 触发缓存（用本测试 fixture）
        $prop = new ReflectionProperty(Model::class, 'links');
        $prop->setAccessible(true);
        $fakeQuery = new \stdClass();
        $prop->setValue(null, [\ThinkOrm\Tests\Fixture\User::class => $fakeQuery]);

        $this->assertNotEmpty($this->getModelLinks());

        // 调用 reconnectDb（内部会先清 Model::$links，然后重建连接）
        $m = new ReflectionMethod($this->worker, 'reconnectDb');
        $m->setAccessible(true);
        $m->invoke($this->worker);

        // 重连后 Model::$links 应被清空
        $this->assertSame([], $this->getModelLinks());
    }

    public function testReconnectDbRebuildsConnection()
    {
        // 取重连前 connection id
        $beforeId = Db::query('SELECT CONNECTION_ID() AS id')[0]['id'];

        $m = new ReflectionMethod($this->worker, 'reconnectDb');
        $m->setAccessible(true);
        $m->invoke($this->worker);

        // 重连后 connection id 应不同（清池后下次 query 重建）
        $afterId = Db::query('SELECT CONNECTION_ID() AS id')[0]['id'];
        $this->assertNotEquals($beforeId, $afterId, 'reconnectDb 应该强制重建 PDO 连接');
    }

    // --------------------------------------------------------------
    // 持久化策略：cli vs phpfpm
    // --------------------------------------------------------------

    public function testCliDaemonDisablesPersistentByDefault()
    {
        // 模拟 run_daemon.php 的配置：cli 下 params = []
        \ThinkOrm\Orm::reset();
        \ThinkOrm\Orm::boot([
            'database' => [
                'type'            => 'mysql',
                'hostname'        => getenv('TORM_DB_HOST') ?: '127.0.0.1',
                'hostport'        => getenv('TORM_DB_PORT') ?: 3306,
                'database'        => getenv('TORM_DB_NAME') ?: 'think_orm_test',
                'username'        => getenv('TORM_DB_USER') ?: 'root',
                'password'        => getenv('TORM_DB_PASS') ?: '123456',
                'params'          => [],                              // ★ cli 关
                'break_reconnect' => true,                              // ★ cli 开
            ],
        ]);

        Db::clear();
        // 触发一次实际连接
        Db::query('SELECT 1');

        // 取底层 PDO，验证非持久化
        $pdo = $this->getUnderlyingPdo();
        $this->assertFalse(
            (bool)$pdo->getAttribute(PDO::ATTR_PERSISTENT),
            'cli 守护进程默认不应开启 persistent'
        );
    }

    public function testPhpFpmEnablesPersistentViaParams()
    {
        // 模拟 phpfpm 配置：params 含 ATTR_PERSISTENT=true
        \ThinkOrm\Orm::reset();
        \ThinkOrm\Orm::boot([
            'database' => [
                'type'            => 'mysql',
                'hostname'        => getenv('TORM_DB_HOST') ?: '127.0.0.1',
                'hostport'        => getenv('TORM_DB_PORT') ?: 3306,
                'database'        => getenv('TORM_DB_NAME') ?: 'think_orm_test',
                'username'        => getenv('TORM_DB_USER') ?: 'root',
                'password'        => getenv('TORM_DB_PASS') ?: '123456',
                'params'          => [PDO::ATTR_PERSISTENT => true],   // ★ phpfpm 开
                'break_reconnect' => false,                             // ★ phpfpm 不依赖此机制
            ],
        ]);

        Db::clear();
        Db::query('SELECT 1');

        $pdo = $this->getUnderlyingPdo();
        $this->assertTrue(
            (bool)$pdo->getAttribute(PDO::ATTR_PERSISTENT),
            'phpfpm 应通过 params 启用 persistent'
        );
    }

    // --------------------------------------------------------------
    // 完整生命周期（限次迭代）
    // --------------------------------------------------------------

    public function testWorkerRunsLimitedIterationsThenExits()
    {
        $counter = 0;
        $worker = new class($counter) extends BaseWorker {
            private $c;
            public function __construct(&$c) {
                $this->c = &$c;
                $this->workerName = 'test-limited';
                $this->tickInterval = 50000; // 50ms
                $this->heartbeatInterval = 999; // 不触发心跳
            }
            protected function onTick(): void {
                $this->c++;
            }
        };

        $worker->setMaxIterations(3);
        $worker->start();

        $this->assertSame(3, $counter);
        $this->assertSame(3, $worker->getIteration());
    }

    public function testWorkerTriggersHeartbeatDuringLongRun()
    {
        $heartbeatCount = 0;
        $worker = new class($heartbeatCount) extends BaseWorker {
            private $c;
            public function __construct(&$c) {
                $this->c = &$c;
                $this->workerName = 'test-heartbeat';
                $this->tickInterval = 50000;  // 50ms tick
                $this->heartbeatInterval = 0; // 每 tick 都心跳
            }
            protected function onTick(): void {}
            protected function heartbeat(): void {
                $this->c++;
                parent::heartbeat();
            }
        };

        $worker->setMaxIterations(3);
        $worker->start();

        $this->assertGreaterThanOrEqual(3, $heartbeatCount);
    }

    // --------------------------------------------------------------
    // 错误恢复
    // --------------------------------------------------------------

    public function testWorkerRecoversFromSimulatedDbBreak()
    {
        $worker = new class extends BaseWorker {
            public $tickCount = 0;
            public function __construct() {
                $this->workerName = 'test-recover';
                $this->tickInterval = 50000;
                $this->heartbeatInterval = 999;
            }
            protected function onTick(): void {
                $this->tickCount++;
                if ($this->tickCount === 1) {
                    // 第一次 tick 抛 DB 断线
                    throw new PDOException('MySQL server has gone away');
                }
                // 后续 tick 正常
            }
        };

        $worker->setMaxIterations(3);
        $worker->start();

        $this->assertSame(3, $worker->tickCount, '应该重试到第 3 次 tick');
        $this->assertSame(1, $worker->getErrorCount(), '应有 1 个错误被记录');
        $this->assertSame(1, $worker->getReconnectCount(), '应触发 1 次重连');
    }

    public function testWorkerDoesNotReconnectOnBusinessError()
    {
        $worker = new class extends BaseWorker {
            public $tickCount = 0;
            public function __construct() {
                $this->workerName = 'test-no-recover';
                $this->tickInterval = 50000;
                $this->heartbeatInterval = 999;
            }
            protected function onTick(): void {
                $this->tickCount++;
                if ($this->tickCount === 1) {
                    // 业务异常（非 DB 断线）→ 不应触发重连
                    throw new RuntimeException('business validation failed');
                }
            }
        };

        $worker->setMaxIterations(2);
        $worker->start();

        $this->assertSame(2, $worker->tickCount);
        $this->assertSame(1, $worker->getErrorCount());
        $this->assertSame(0, $worker->getReconnectCount(), '业务异常不应触发重连');
    }

    // --------------------------------------------------------------
    // helpers
    // --------------------------------------------------------------

    private function callCheckDbBreak(Throwable $e): bool
    {
        $m = new ReflectionMethod($this->worker, 'checkDbBreak');
        $m->setAccessible(true);
        return $m->invoke($this->worker, $e);
    }

    private function getPrivate($obj, string $name)
    {
        $p = new ReflectionProperty(BaseWorker::class, $name);
        $p->setAccessible(true);
        return $p->getValue($obj);
    }

    private function getModelLinks(): array
    {
        $p = new ReflectionProperty(Model::class, 'links');
        $p->setAccessible(true);
        return $p->getValue();
    }

    /**
     * 从 Db facade 取出底层 PDO（穿透 Connection / Query 两层）
     */
    private function getUnderlyingPdo(): PDO
    {
        // Db::$instance 是按 config key 缓存的 Connection 实例
        $instProp = new ReflectionProperty(Db::class, 'instance');
        $instProp->setAccessible(true);
        $instances = $instProp->getValue();
        $this->assertNotEmpty($instances, 'Db::$instance 应有 Connection 实例');

        $conn = reset($instances);
        $linkProp = new ReflectionProperty($conn, 'linkID');
        $linkProp->setAccessible(true);
        $pdo = $linkProp->getValue($conn);
        $this->assertInstanceOf(PDO::class, $pdo);

        return $pdo;
    }
}
