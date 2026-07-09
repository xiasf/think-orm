<?php

namespace ThinkOrm\Tests\Integration;

use think\Db;
use ThinkOrm\Tests\IntegrationTestCase;

/**
 * @group integration
 */
class SubqueryTest extends IntegrationTestCase
{
    public function testBuildSql()
    {
        $sql = Db::name('users')->where('age', '>', 10)->buildSql();
        $this->assertIsString($sql);
        $this->assertStringContainsString('SELECT', $sql);
        $this->assertStringContainsString('age', $sql);
    }

    public function testSubqueryInWhere()
    {
        $uid = $this->seedUser(['age' => 50]);
        Db::name('posts')->insert(['user_id' => $uid, 'title' => 'p1']);

        // 子查询：找出 age > 10 的 user 的 posts（闭包形式）
        $rows = Db::name('posts')->where('user_id', 'in', function ($q) {
            $q->name('users')->field('id')->where('age', '>', 10);
        })->select();
        $this->assertCount(1, $rows);
    }

    public function testFetchSqlWithoutExecution()
    {
        $sql = Db::name('users')->where('id', 1)->fetchSql(true)->find();
        $this->assertIsString($sql);
        $this->assertStringContainsString('LIMIT', $sql);
    }
}
