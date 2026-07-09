<?php

namespace ThinkOrm\Tests\Integration;

use think\Db;
use think\db\Expression;
use ThinkOrm\Tests\IntegrationTestCase;

/**
 * @group integration
 */
class QueryBuilderTest extends IntegrationTestCase
{
    public function testWhereString()
    {
        $id = $this->seedUser(['name' => 'foo']);
        $row = Db::name('users')->where('id=' . $id)->find();
        $this->assertSame('foo', $row['name']);
    }

    public function testWhereArrayEq()
    {
        $this->seedUser(['name' => 'foo']);
        $row = Db::name('users')->where(['name' => 'foo'])->find();
        $this->assertNotNull($row);
    }

    public function testWhereOperator()
    {
        $this->seedUser(['age' => 10]);
        $this->seedUser(['age' => 20]);
        $this->seedUser(['age' => 30]);
        $rows = Db::name('users')->where('age', '>', 10)->order('age')->select();
        $this->assertCount(2, $rows);
    }

    public function testWhereIn()
    {
        $this->seedUser(['age' => 1]);
        $this->seedUser(['age' => 2]);
        $this->seedUser(['age' => 3]);
        $rows = Db::name('users')->where('age', 'in', '1,3')->order('age')->select();
        $this->assertCount(2, $rows);
    }

    public function testWhereNotIn()
    {
        foreach (range(1, 4) as $i) $this->seedUser(['age' => $i]);
        $rows = Db::name('users')->where('age', 'not in', [1, 2])->order('age')->select();
        $this->assertCount(2, $rows);
        $this->assertSame('3', (string) $rows[0]['age']);
    }

    public function testWhereBetween()
    {
        foreach (range(1, 10) as $i) $this->seedUser(['age' => $i]);
        $rows = Db::name('users')->where('age', 'between', '3,5')->order('age')->select();
        $this->assertCount(3, $rows);
    }

    public function testWhereLike()
    {
        $this->seedUser(['name' => 'alice']);
        $this->seedUser(['name' => 'bob']);
        $rows = Db::name('users')->where('name', 'like', 'a%')->select();
        $this->assertCount(1, $rows);
        $this->assertSame('alice', $rows[0]['name']);
    }

    public function testWhereNull()
    {
        $this->seedUser(['age' => null]);
        $this->seedUser(['age' => 10]);
        $rows = Db::name('users')->where('age', 'null')->select();
        $this->assertCount(1, $rows);
    }

    public function testWhereOr()
    {
        $this->seedUser(['name' => 'a', 'age' => 1]);
        $this->seedUser(['name' => 'b', 'age' => 2]);
        $this->seedUser(['name' => 'c', 'age' => 3]);
        $rows = Db::name('users')->where('name', 'a')->whereOr('name', 'c')->order('age')->select();
        $this->assertCount(2, $rows);
    }

    public function testWhereExpression()
    {
        $this->seedUser(['age' => 10]);
        $this->seedUser(['age' => 20]);
        // 使用表达式：age = age + 5（仅检查表达式不报错）
        $sql = Db::name('users')->where('age', 'exp', new Expression('age > 5'))->fetchSql(true)->select();
        $this->assertStringContainsString('age > 5', $sql);
    }

    public function testJoin()
    {
        $uid = $this->seedUser(['name' => 'u1']);
        Db::name('posts')->insert(['user_id' => $uid, 'title' => 'p1']);
        Db::name('posts')->insert(['user_id' => $uid, 'title' => 'p2']);
        $rows = Db::name('users')->alias('u')->join('posts p', 'p.user_id=u.id')->where('u.id', $uid)->select();
        $this->assertCount(2, $rows);
    }

    public function testLeftJoin()
    {
        $uid = $this->seedUser();
        // user 没有 posts
        $rows = Db::name('users')->alias('u')->join('posts p', 'p.user_id=u.id', 'LEFT')->where('u.id', $uid)->select();
        $this->assertCount(1, $rows);
    }

    public function testGroupAndHaving()
    {
        $this->seedUser(['age' => 10]);
        $this->seedUser(['age' => 10]);
        $this->seedUser(['age' => 20]);
        $rows = Db::name('users')->field('age, count(*) as cnt')->group('age')->having('cnt >= 2')->select();
        $this->assertCount(1, $rows);
        $this->assertSame('10', (string) $rows[0]['age']);
    }

    public function testOrderLimit()
    {
        for ($i = 1; $i <= 5; $i++) $this->seedUser(['age' => $i]);
        $rows = Db::name('users')->order('age desc')->limit(2)->select();
        $this->assertCount(2, $rows);
        $this->assertSame('5', (string) $rows[0]['age']);
    }

    public function testPage()
    {
        for ($i = 1; $i <= 10; $i++) $this->seedUser(['age' => $i]);
        $rows = Db::name('users')->order('age')->page(2, 3)->select();
        $this->assertCount(3, $rows);
        $this->assertSame('4', (string) $rows[0]['age']);
    }

    public function testField()
    {
        $this->seedUser(['name' => 'a', 'email' => 'b']);
        $row = Db::name('users')->field('name')->find();
        $this->assertSame(['name' => 'a'], $row);
    }

    public function testDistinct()
    {
        $this->seedUser(['age' => 5]);
        $this->seedUser(['age' => 5]);
        $this->seedUser(['age' => 6]);
        $cnt = Db::name('users')->distinct(true)->field('age')->count('DISTINCT age');
        $this->assertGreaterThanOrEqual(2, $cnt);
    }

    public function testLockForUpdate()
    {
        $id = $this->seedUser();
        $sql = Db::name('users')->where('id', $id)->lock(true)->fetchSql(true)->find();
        $this->assertStringContainsString('FOR UPDATE', $sql);
    }

    public function testIncDec()
    {
        $id = $this->seedUser(['age' => 10]);
        Db::name('users')->where('id', $id)->inc('age')->update();
        $this->assertSame('11', (string) Db::name('users')->where('id', $id)->value('age'));
        Db::name('users')->where('id', $id)->dec('age', 2)->update();
        $this->assertSame('9', (string) Db::name('users')->where('id', $id)->value('age'));
    }

    public function testExpInc()
    {
        $id = $this->seedUser(['age' => 10]);
        Db::name('users')->where('id', $id)->update(['age' => ['inc', 5]]);
        $this->assertSame('15', (string) Db::name('users')->where('id', $id)->value('age'));
    }
}
