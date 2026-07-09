<?php

namespace ThinkOrm\Tests;

use think\Config;
use think\Db;
use think\Loader;
use ThinkOrm\Orm;

/**
 * Integration 测试基类：每个测试方法运行前清空所有表，确保隔离
 */
abstract class IntegrationTestCase extends \PHPUnit\Framework\TestCase
{
    protected static $tablesToTruncate = [
        'posts_tags',
        'tags',
        'comments',
        'images',
        'posts',
        'users',
        'logs',
        // demo schema - di 模块
        'di_notice',
        'di_smartpark',
        // demo schema - parkinglot 模块
        'pt_car_parking',
        'pt_car_car_owner',
        'pt_car_owner',
        'pt_car',
        'pt_parkinglot',
        'pt_user',
        'pt_smartpark',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // Unit 测试可能调过 Config::reset()，重新 boot 以确保 DB 配置存在
        self::ensureBoot();

        // 清理 Query 内字段结构缓存（避免使用上次测试残留的 schema 信息）
        Loader::clearInstance();
        Db::clear();

        foreach (self::$tablesToTruncate as $tbl) {
            Db::execute("TRUNCATE TABLE `{$tbl}`");
        }
    }

    protected function tearDown(): void
    {
        // 关闭可能残留的事务
        try {
            Db::rollback();
        } catch (\Throwable $e) {}
        parent::tearDown();
    }

    /**
     * 重新读取环境变量并 boot ORM，恢复 Unit 测试破坏的配置
     */
    private static function ensureBoot()
    {
        $host    = getenv('TORM_DB_HOST')    ?: '127.0.0.1';
        $port    = getenv('TORM_DB_PORT')    ?: '3306';
        $name    = getenv('TORM_DB_NAME')    ?: 'think_orm_test';
        $user    = getenv('TORM_DB_USER')    ?: 'root';
        $pass    = getenv('TORM_DB_PASS')    ?: '123456';
        $charset = getenv('TORM_DB_CHARSET') ?: 'utf8mb4';
        $prefix  = getenv('TORM_DB_PREFIX')  ?: '';

        Orm::boot([
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
    }

    /**
     * 插入一条用户并返回 id
     */
    protected function seedUser(array $overrides = []): int
    {
        $defaults = ['name' => 'u', 'email' => 'e', 'age' => 20, 'is_active' => 1];
        $data = array_merge($defaults, $overrides);
        return Db::name('users')->insertGetId($data);
    }
}
