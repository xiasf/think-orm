<?php
// +----------------------------------------------------------------------
// | 队列消费 Worker —— BaseWorker 的具体实现示例
// |
// | 场景：从 jobs 表轮询未处理任务，处理后标记为 done。
// | 演示要点：
// |   1. onTick 业务逻辑（拉任务 → 处理 → 更新状态）
// |   2. 长事务不要包主循环（守护进程几小时一个事务会拖死 InnoDB）
// |   3. 单条任务级事务（失败回滚单条，不影响其他任务）
// +----------------------------------------------------------------------

namespace example\daemon;

use think\Db;

class QueueWorker extends BaseWorker
{
    /** @var string 任务表 */
    protected $jobsTable = 'daemon_jobs';

    /** @var int 单次拉取条数 */
    protected $batchSize = 10;

    /** @var int 单条任务最大处理时长（秒） */
    protected $jobTimeout = 30;

    public function __construct()
    {
        $this->workerName       = 'queue';
        $this->tickInterval     = 2 * 1000000;   // 2 秒 tick
        $this->heartbeatInterval = 30;            // 30 秒心跳
    }

    protected function onWorkerStart(): void
    {
        // 启动时确保任务表存在（开发期便利；生产环境应该走 migration）
        $exists = Db::query("SHOW TABLES LIKE '{$this->jobsTable}'");
        if (empty($exists)) {
            Db::execute("
                CREATE TABLE `{$this->jobsTable}` (
                    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `payload`    VARCHAR(255)    NOT NULL,
                    `status`     TINYINT         NOT NULL DEFAULT 0 COMMENT '0=pending,1=done,2=failed',
                    `created_at` DATETIME        NULL,
                    `done_at`    DATETIME        NULL,
                    PRIMARY KEY (`id`),
                    KEY `idx_status` (`status`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            $this->log('init-table', "created {$this->jobsTable}");
        }

        // demo：表空时灌几条种子任务（生产环境删掉此分支）
        $pending = Db::name($this->jobsTable)->where('status', 0)->count();
        if ($pending === 0) {
            for ($i = 1; $i <= 5; $i++) {
                Db::name($this->jobsTable)->insert([
                    'payload'    => "task#{$i}-" . substr(md5(uniqid()), 0, 6),
                    'status'     => 0,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
            $this->log('seed', "灌入 5 条测试任务");
        }
    }

    protected function onTick(): void
    {
        // 1. 拉一批未处理任务（FOR UPDATE SKIP LOCKED 是 8.0+ 特性，5.7 用 LIMIT 简单粗暴）
        $jobs = Db::name($this->jobsTable)
            ->where('status', 0)
            ->order('id')
            ->limit($this->batchSize)
            ->select();

        if (empty($jobs)) {
            return; // 无任务，等下个 tick
        }

        foreach ($jobs as $job) {
            $this->processJob($job);
        }
    }

    /**
     * 处理单条任务 —— 单条级事务（失败回滚不影响其他任务）
     */
    private function processJob(array $job): void
    {
        $this->log('job-start', "id={$job['id']} payload={$job['payload']}");

        Db::startTrans();
        try {
            // 模拟业务处理
            $this->doBusinessLogic($job['payload']);

            // 标记完成
            Db::name($this->jobsTable)
                ->where('id', $job['id'])
                ->update([
                    'status'   => 1,
                    'done_at'  => date('Y-m-d H:i:s'),
                ]);

            Db::commit();
            $this->log('job-done', "id={$job['id']}");
        } catch (\Throwable $e) {
            Db::rollback();

            // 失败也落库（避免重复拉取；监控看 status=2 的）
            Db::name($this->jobsTable)
                ->where('id', $job['id'])
                ->update(['status' => 2]);

            // 重新抛出，让外层 catch 决定是否触发重连逻辑
            throw $e;
        }
    }

    /**
     * 真实业务逻辑（demo 里就是 sleep + 偶尔失败）
     */
    private function doBusinessLogic(string $payload): void
    {
        // 模拟处理耗时
        usleep(500000); // 0.5s

        // 模拟 10% 失败率（演示 onError / 单条回滚）
        if (random_int(1, 10) === 5) {
            throw new \RuntimeException("simulated business failure for payload={$payload}");
        }
    }
}
