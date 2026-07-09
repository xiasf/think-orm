<?php

namespace ThinkOrm\Tests\Integration;

use think\Db;
use ThinkOrm\Tests\IntegrationTestCase;

/**
 * @group integration
 *
 * 闭包 WHERE 测试：支持闭包方式构造复杂 OR/嵌套
 */
class ClosureWhereTest extends IntegrationTestCase
{
    public function testClosureWhereAnd()
    {
        $this->seedUser(['name' => 'a', 'age' => 10]);
        $this->seedUser(['name' => 'b', 'age' => 20]);

        $rows = Db::name('users')
            ->where(function ($q) {
                $q->where('age', '>=', 10)->where('age', '<=', 15);
            })
            ->select();
        $this->assertCount(1, $rows);
    }

    public function testClosureWhereOr()
    {
        $this->seedUser(['age' => 5]);
        $this->seedUser(['age' => 50]);
        $this->seedUser(['age' => 100]);

        $rows = Db::name('users')
            ->where(function ($q) {
                $q->where('age', '<=', 5)->whereOr('age', '>=', 100);
            })
            ->order('age')
            ->select();
        $this->assertCount(2, $rows);
    }

    public function testNestedClosure()
    {
        $this->seedUser(['name' => 'a', 'age' => 10]);
        $this->seedUser(['name' => 'b', 'age' => 20]);
        $this->seedUser(['name' => 'c', 'age' => 30]);

        $rows = Db::name('users')
            ->where('name', '<>', 'c')
            ->where(function ($q) {
                $q->where('age', 20)->whereOr('age', 10);
            })
            ->order('age')
            ->select();
        $this->assertCount(2, $rows);
    }

    public function testClosureInWhereOr()
    {
        $this->seedUser(['name' => 'a', 'age' => 10]);
        $this->seedUser(['name' => 'b', 'age' => 20]);
        $this->seedUser(['name' => 'c', 'age' => 30]);

        $rows = Db::name('users')
            ->where('name', 'a')
            ->whereOr(function ($q) {
                $q->where('name', 'b')->where('age', 20);
            })
            ->order('age')
            ->select();
        $this->assertCount(2, $rows);
    }

    public function testWhereExistsLike()
    {
        $uid = $this->seedUser(['age' => 100]);
        Db::name('posts')->insert(['user_id' => $uid, 'title' => 'rare']);

        // 子查询：找出 age > 50 的 user 的 posts（闭包形式）
        $rows = Db::name('posts')->where('user_id', 'in', function ($q) {
            $q->name('users')->field('id')->where('age', '>', 50);
        })->select();
        $this->assertCount(1, $rows);
    }
}
