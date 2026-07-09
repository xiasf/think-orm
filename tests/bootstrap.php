<?php
// +----------------------------------------------------------------------
// | think-orm 测试 bootstrap
// +----------------------------------------------------------------------
// | 1) 加载 composer autoload
// | 2) \ThinkOrm\Orm::boot() 注入测试 DB 配置
// | 3) 用原生 PDO 执行 fixtures/schema.sql 重建表
// | 4) \think\Db::clear() 重置连接缓存，让 Query 用全新连接
// +----------------------------------------------------------------------

require __DIR__ . '/../vendor/autoload.php';

use think\Db;

$host    = getenv('TORM_DB_HOST')    ?: '127.0.0.1';
$port    = getenv('TORM_DB_PORT')    ?: '3306';
$name    = getenv('TORM_DB_NAME')    ?: 'think_orm_test';
$user    = getenv('TORM_DB_USER')    ?: 'root';
$pass    = getenv('TORM_DB_PASS')    ?: '123456';
$charset = getenv('TORM_DB_CHARSET') ?: 'utf8mb4';
$prefix  = getenv('TORM_DB_PREFIX')  ?: '';

\ThinkOrm\Orm::boot([
    'database' => [
        'type'            => 'mysql',
        'hostname'        => $host,
        'hostport'        => $port,
        'database'        => $name,
        'username'        => $user,
        'password'        => $pass,
        'charset'         => $charset,
        'prefix'          => $prefix,
        'debug'           => false,
        'fields_strict'   => false,
        'resultset_type'  => 'array',
        'auto_timestamp'  => false,
        'datetime_format' => 'Y-m-d H:i:s',
    ],
]);

// 原生 PDO 准备 schema
$dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $host, $port, $name, $charset);
$dbAvailable = true;
try {
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (Throwable $e) {
    $dbAvailable = false;
    fwrite(STDERR, "\n[think-orm tests] MySQL 连接失败：{$e->getMessage()}\n");
    fwrite(STDERR, "  环境变量：TORM_DB_HOST/TORM_DB_NAME/TORM_DB_USER/TORM_DB_PASS\n");
    fwrite(STDERR, "  Unit 测试可继续；Integration 测试将无法运行。\n\n");
}

if ($dbAvailable) {
    foreach (['fixtures/schema.sql', 'fixtures/example_schema.sql'] as $sqlFile) {
        $sql = file_get_contents(__DIR__ . '/' . $sqlFile);
        if ($sql === false) {
            fwrite(STDERR, "[think-orm tests] {$sqlFile} 读取失败\n");
            continue;
        }
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
            if ($stmt === '' || $stmt === "\n") {
                continue;
            }
            try {
                $pdo->exec($stmt);
            } catch (Throwable $e) {
                fwrite(STDERR, "[think-orm tests] SQL 执行失败: {$e->getMessage()}\n  in: {$stmt}\n");
            }
        }
    }
}

// 清理 ORM 缓存（Query 内字段结构缓存、Connection 单例）
Db::clear();
