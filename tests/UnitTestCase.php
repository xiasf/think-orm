<?php
// +----------------------------------------------------------------------
// | think-orm Unit 测试基类
// +----------------------------------------------------------------------

namespace ThinkOrm\Tests;

use PHPUnit\Framework\TestCase;
use ThinkOrm\Orm;

abstract class UnitTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Unit 测试不需要真实 DB 连接，但需要 ORM 内部常量
        Orm::boot([
            'database' => [
                'type'     => 'mysql',
                'hostname' => '127.0.0.1',
                'database' => 'unit_test_stub',
                'username' => 'root',
                'password' => '',
                'prefix'   => '',
            ],
        ]);
    }
}
