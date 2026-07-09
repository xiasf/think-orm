<?php
// +----------------------------------------------------------------------
// | 守护进程启动示例
// |
// | 用法（需要 MySQL，复用 example 数据库）：
// |   php example/run_daemon.php                 # 无限循环（生产用法）
// |   php example/run_daemon.php --max-tick=20   # 跑 20 个 tick 后退出（demo / 调试）
// |
// | 关键演示点：
// |   1. cli 模式：**关闭** PDO::ATTR_PERSISTENT，**开启** break_reconnect
// |      （守护进程常驻，persistent 不带来额外收益，反而阻止 break_reconnect 关闭僵尸连接）
// |
// |   2. 模拟 DB 断线（可选）：在另一个终端
// |      mysql -uroot -p123456 -e "KILL <connection_id>"
// |      观察日志里 [reconnect] 字样
// +----------------------------------------------------------------------

require __DIR__ . '/../vendor/autoload.php';

use example\daemon\QueueWorker;
use think\App;
use think\Db;
use ThinkOrm\Orm;

// CLI 才能跑（避免 phpfpm 下误启动）
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "本脚本必须在 CLI 下运行\n");
    exit(1);
}

App::$namespace = 'app';

// === 关键：守护进程专用配置 ===
$isCliPersistent = false; // **重要：cli 守护进程关闭 persistent**

Orm::boot([
    'database' => [
        'type'            => 'mysql',
        'hostname'        => getenv('TORM_DB_HOST') ?: '127.0.0.1',
        'hostport'        => getenv('TORM_DB_PORT') ?: 3306,
        'database'        => getenv('TORM_DB_NAME') ?: 'think_orm_example',
        'username'        => getenv('TORM_DB_USER') ?: 'root',
        'password'        => getenv('TORM_DB_PASS') ?: '123456',
        'charset'         => 'utf8mb4',
        'prefix'          => '',
        // ★ 守护进程关键配置
        'params'          => $isCliPersistent
                            ? [\PDO::ATTR_PERSISTENT => true]
                            : [],                              // cli 不开持久化
        'break_reconnect' => true,                              // ★ 守护进程必须开
        'debug'           => getenv('TORM_DEBUG') === '1',
    ],
]);

// 启动 worker（带可选 max-tick 用于 demo 退出）
// 任务 seed 由 QueueWorker::onWorkerStart 自动完成（首次启动时灌 5 条 demo 任务）
$maxTick = null;
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--max-tick=(\d+)$/', $arg, $m)) {
        $maxTick = (int)$m[1];
    }
}

$worker = new QueueWorker();

if ($maxTick !== null) {
    echo "[demo] max-tick={$maxTick}（限次模式，跑完自动退出）\n";
    $worker->setMaxIterations($maxTick);
} else {
    echo "[prod] 无限循环模式（Ctrl+C 退出，或 kill -TERM <pid>）\n";
}

$worker->start();
