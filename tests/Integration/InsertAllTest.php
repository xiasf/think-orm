<?php

namespace ThinkOrm\Tests\Integration;

use think\Db;
use ThinkOrm\Tests\IntegrationTestCase;

/**
 * @group integration
 */
class InsertAllTest extends IntegrationTestCase
{
    public function testInsertAllMultiple()
    {
        $rows = [];
        for ($i = 1; $i <= 10; $i++) {
            $rows[] = ['name' => 'u' . $i, 'email' => "u{$i}@x", 'age' => $i];
        }
        $n = Db::name('users')->insertAll($rows);
        $this->assertSame(10, $n);
        $this->assertSame(10, Db::name('users')->count());
    }

    public function testInsertAllWithBatchSize()
    {
        $rows = [];
        for ($i = 1; $i <= 100; $i++) {
            $rows[] = ['name' => 'u' . $i, 'email' => "u{$i}@x", 'age' => $i];
        }
        $n = Db::name('users')->insertAll($rows, 25);  // 每批 25
        $this->assertSame(100, $n);
        $this->assertSame(100, Db::name('users')->count());
    }

    public function testInsertAllReplace()
    {
        $id = Db::name('users')->insertGetId(['name' => 'u', 'email' => 'u@x', 'age' => 1]);
        // REPLACE 模式：以相同 PK 再写一遍
        Db::name('users')->insertAll([
            ['id' => $id, 'name' => 'u2', 'email' => 'u@x', 'age' => 2],
        ], true);
        $this->assertSame('u2', Db::name('users')->where('id', $id)->value('name'));
        $this->assertSame(1, Db::name('users')->count());
    }

    public function testInsertGetIdWithCompositePk()
    {
        // logs 表是复合主键
        $n = Db::name('logs')->insert([
            'log_date' => '2024-01-01', 'seq' => 1, 'level' => 'INFO', 'message' => 'm1',
        ]);
        $this->assertSame(1, $n);
        $row = Db::name('logs')->where('log_date', '2024-01-01')->where('seq', 1)->find();
        $this->assertSame('INFO', $row['level']);
    }
}
