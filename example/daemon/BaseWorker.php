<?php
// +----------------------------------------------------------------------
// | 守护进程基类 —— 在 cli 常驻进程（workerman / swoole / while(true) loop）
// | 中安全使用 think-orm 的最小框架。
// |
// | 解决 3 个核心问题：
// |   1. 长连接被 MySQL wait_timeout 切断（默认 8 小时，但 DBA 可能改更短）
// |      → 启用 break_reconnect + isBreak 关键词识别 + 自动 close() 重连
// |
// |   2. Model::$links 静态缓存持有僵尸 Connection 对象
// |      → reconnectDb() 用反射清空，让下次 Model::db() 拿新连接
// |
// |   3. 心跳：避免业务跑完才发现连接已断
// |      → 定期 SELECT 1 主动探活
// |
// | 设计原则：单文件、零外部依赖、不依赖 pcntl（Windows 也能跑）
// +----------------------------------------------------------------------

namespace example\daemon;

use think\Db;
use think\Model;
use Throwable;

abstract class BaseWorker
{
    /** @var string worker 名称（日志 / 标识用） */
    protected $workerName = 'worker';

    /** @var int 主循环间隔（微秒；默认 1 秒） */
    protected $tickInterval = 1000000;

    /** @var int 心跳间隔（秒；默认 30s 探活一次） */
    protected $heartbeatInterval = 30;

    /** @var bool 收到退出信号 */
    private $stopRequested = false;

    /** @var float 上次心跳时间戳 */
    private $lastHeartbeat = 0.0;

    /** @var int 累计错误数（监控用） */
    private $errorCount = 0;

    /** @var int 累计重连次数（监控用） */
    private $reconnectCount = 0;

    /** @var int|null demo / 调试用：限次迭代（null = 无限） */
    private $maxIterations = null;

    /** @var int 已运行迭代数 */
    private $iteration = 0;

    /**
     * 启动 worker 主循环
     *
     * 用法：
     *   $w = new MyWorker();
     *   $w->start();
     */
    public function start(): void
    {
        $this->log('start', "worker={$this->workerName} starting");

        // 注册信号处理（pcntl 不存在则跳过，依赖 windows / 无 pcntl 环境）
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, [$this, 'handleSignal']);
            pcntl_signal(SIGINT,  [$this, 'handleSignal']);
            pcntl_async_signals(true);
        }

        try {
            $this->onBeforeStart();
            $this->initDb();
            $this->onWorkerStart();
        } catch (Throwable $e) {
            $this->onError($e);
            throw $e;
        }

        while (!$this->stopRequested) {
            $this->iteration++;
            try {
                // 心跳（每 heartbeatInterval 秒探活一次）
                if (microtime(true) - $this->lastHeartbeat >= $this->heartbeatInterval) {
                    $this->heartbeat();
                    $this->lastHeartbeat = microtime(true);
                }

                // 业务 tick（子类实现）
                $this->onTick();
            } catch (Throwable $e) {
                $this->errorCount++;
                $this->onError($e);

                // 是否是 DB 断线 → 重连
                if ($this->checkDbBreak($e)) {
                    $this->log('reconnect', "detected db break, reconnecting: {$e->getMessage()}");
                    try {
                        $this->reconnectDb();
                        $this->reconnectCount++;
                    } catch (Throwable $reconnErr) {
                        $this->log('reconnect-failed', $reconnErr->getMessage());
                    }
                }
            }

            // pcntl 信号分发
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }

            usleep($this->tickInterval);

            // demo / 调试：限次迭代后自动退出
            if ($this->maxIterations !== null && $this->iteration >= $this->maxIterations) {
                $this->log('demo-limit', "reached maxIterations={$this->maxIterations}, stopping");
                $this->stopRequested = true;
            }
        }

        $this->onWorkerStop();
        $this->log('stop', "worker={$this->workerName} stopped (errors={$this->errorCount} reconnects={$this->reconnectCount})");
    }

    /**
     * 信号处理（SIGTERM / SIGINT）
     */
    public function handleSignal(int $signo): void
    {
        $this->log('signal', "received signo={$signo}, will stop after current tick");
        $this->stopRequested = true;
    }

    // ------------------------------------------------------------------
    // 钩子：子类按需覆盖
    // ------------------------------------------------------------------

    /** 启动前调用一次（注册资源、加载配置等） */
    protected function onBeforeStart(): void {}

    /** DB 初始化后、主循环开始前调用 */
    protected function onWorkerStart(): void {}

    /** 主循环每次 tick 业务逻辑（子类必须实现） */
    abstract protected function onTick(): void;

    /** 异常统一入口（记录日志 / 上报） */
    protected function onError(Throwable $e): void
    {
        $this->log('error', $e->getMessage());
    }

    /** 退出前清理 */
    protected function onWorkerStop(): void {}

    // ------------------------------------------------------------------
    // DB 生命周期管理
    // ------------------------------------------------------------------

    /**
     * 初始化 DB 连接 —— 启动时强制 SELECT 1 预连接（避免 lazy 引发首次业务请求即失败）
     */
    protected function initDb(): void
    {
        Db::query('SELECT 1');
        $this->lastHeartbeat = microtime(true);
        $this->log('init-db', 'connection established');
    }

    /**
     * 心跳 —— 简单 SELECT 1 探活；失败抛 PDOException 触发外层 catch
     */
    protected function heartbeat(): void
    {
        Db::query('SELECT 1');
    }

    /**
     * 识别错误是否为 DB 断线
     * 关键词列表参考 Connection::isBreak()，匹配 typical MySQL/PHP 错误
     */
    protected function checkDbBreak(Throwable $e): bool
    {
        $keywords = [
            'server has gone away',
            'no connection to the server',
            'Lost connection',
            'is dead or not enabled',
            'Error while sending',
            'decryption failed or bad record mac',
            'server closed the connection unexpectedly',
            'SSL connection has been closed unexpectedly',
            'Error writing data to the connection',
            'Resource deadlock avoided',
            'failed with errno',
            'Broken pipe',
        ];
        $msg = $e->getMessage();
        foreach ($keywords as $kw) {
            if (stripos($msg, $kw) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * 重连 —— 清掉两道缓存
     *
     * 1. Db::$instance：按 config 缓存的 Connection 对象池
     * 2. Model::$links：按 model 类名缓存的 Query 实例（持有 Connection）
     *
     * 这两道缓存不清，break_reconnect 即使在 Connection 层重建了 PDO，
     * Model 仍会拿到老的僵尸 Connection —— 实战中踩过
     */
    protected function reconnectDb(): void
    {
        // 1) 清全局连接池
        Db::clear();

        // 2) 清 Model 类级别 Query 缓存（protected 属性，用反射）
        $prop = new \ReflectionProperty(Model::class, 'links');
        $prop->setAccessible(true);
        $prop->setValue(null, []);

        // 3) 强制重建一次连接（如果失败立即抛出）
        Db::query('SELECT 1');
    }

    // ------------------------------------------------------------------
    // 辅助
    // ------------------------------------------------------------------

    /**
     * 简单日志器（覆盖此方法接入业务日志器）
     */
    protected function log(string $event, string $message): void
    {
        $ts = date('Y-m-d H:i:s');
        echo "[{$ts}] [{$this->workerName}] [{$event}] {$message}\n";
    }

    public function getErrorCount(): int    { return $this->errorCount; }
    public function getReconnectCount(): int{ return $this->reconnectCount; }
    public function getIteration(): int     { return $this->iteration; }

    /**
     * demo / 调试用：限制主循环迭代次数后自动退出（null = 无限循环）
     */
    public function setMaxIterations(?int $n): void { $this->maxIterations = $n; }
}
