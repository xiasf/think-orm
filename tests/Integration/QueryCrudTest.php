<?php

namespace ThinkOrm\Tests\Integration;

use think\Db;
use ThinkOrm\Tests\IntegrationTestCase;

/**
 * @group integration
 */
class QueryCrudTest extends IntegrationTestCase
{
    public function testInsertAndGetLastInsID()
    {
        $id = Db::name('users')->insertGetId(['name' => 'alice', 'email' => 'a@x.com', 'age' => 20]);
        $this->assertGreaterThan(0, $id);
    }

    public function testInsertAll()
    {
        $rows = [
            ['name' => 'a', 'email' => 'a@x.com', 'age' => 1],
            ['name' => 'b', 'email' => 'b@x.com', 'age' => 2],
            ['name' => 'c', 'email' => 'c@x.com', 'age' => 3],
        ];
        $n = Db::name('users')->insertAll($rows);
        $this->assertSame(3, $n);
        $this->assertSame(3, Db::name('users')->count());
    }

    public function testFind()
    {
        $id = $this->seedUser(['name' => 'bob']);
        $row = Db::name('users')->where('id', $id)->find();
        $this->assertIsArray($row);
        $this->assertSame('bob', $row['name']);
    }

    public function testFindReturnsNullWhenEmpty()
    {
        $row = Db::name('users')->where('id', 999999)->find();
        $this->assertNull($row);
    }

    public function testSelect()
    {
        $this->seedUser(['name' => 'a']);
        $this->seedUser(['name' => 'b']);
        $rows = Db::name('users')->order('id')->select();
        $this->assertCount(2, $rows);
        $this->assertSame('a', $rows[0]['name']);
        $this->assertSame('b', $rows[1]['name']);
    }

    public function testValue()
    {
        $id = $this->seedUser(['name' => 'val']);
        $this->assertSame('val', Db::name('users')->where('id', $id)->value('name'));
        $this->assertSame('default', Db::name('users')->where('id', 999)->value('name', 'default'));
    }

    public function testColumn()
    {
        $this->seedUser(['name' => 'a']);
        $this->seedUser(['name' => 'b']);
        $names = Db::name('users')->order('id')->column('name');
        $this->assertSame(['a', 'b'], $names);

        $byId = Db::name('users')->column('name', 'id');
        $this->assertNotEmpty($byId);
    }

    public function testUpdate()
    {
        $id = $this->seedUser(['name' => 'old']);
        $n = Db::name('users')->where('id', $id)->update(['name' => 'new']);
        $this->assertSame(1, $n);
        $this->assertSame('new', Db::name('users')->where('id', $id)->value('name'));
    }

    public function testSetField()
    {
        $id = $this->seedUser(['name' => 'x']);
        Db::name('users')->where('id', $id)->setField('name', 'y');
        $this->assertSame('y', Db::name('users')->where('id', $id)->value('name'));
    }

    public function testSetInc()
    {
        $id = $this->seedUser(['age' => 10]);
        Db::name('users')->where('id', $id)->setInc('age', 5);
        $this->assertSame('15', (string) Db::name('users')->where('id', $id)->value('age'));
    }

    public function testSetDec()
    {
        $id = $this->seedUser(['age' => 10]);
        Db::name('users')->where('id', $id)->setDec('age', 3);
        $this->assertSame('7', (string) Db::name('users')->where('id', $id)->value('age'));
    }

    public function testDelete()
    {
        $id = $this->seedUser();
        $n = Db::name('users')->where('id', $id)->delete();
        $this->assertSame(1, $n);
        $this->assertNull(Db::name('users')->where('id', $id)->find());
    }

    public function testCount()
    {
        $this->seedUser();
        $this->seedUser();
        $this->assertSame(2, Db::name('users')->count());
    }

    public function testMaxMinAvgSum()
    {
        $this->seedUser(['age' => 10]);
        $this->seedUser(['age' => 20]);
        $this->seedUser(['age' => 30]);
        $this->assertSame('30', (string) Db::name('users')->max('age'));
        $this->assertSame('10', (string) Db::name('users')->min('age'));
        $this->assertSame('20', (string) Db::name('users')->avg('age'));
        $this->assertSame('60', (string) Db::name('users')->sum('age'));
    }

    public function testTruncate()
    {
        $this->seedUser();
        $this->seedUser();
        Db::execute('TRUNCATE TABLE users');
        $this->assertSame(0, Db::name('users')->count());
    }

    public function testGetLastSql()
    {
        $this->seedUser();
        $rows = Db::name('users')->select();
        $sql = Db::getLastSql();
        $this->assertStringContainsString('SELECT', $sql);
        $this->assertStringContainsString('users', $sql);
    }

    public function testFetchSql()
    {
        $sql = Db::name('users')->where('age', '>', 10)->fetchSql(true)->select();
        $this->assertIsString($sql);
        $this->assertStringContainsString('SELECT', $sql);
        $this->assertStringContainsString('age', $sql);
    }
}
