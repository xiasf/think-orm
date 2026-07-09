<?php

namespace ThinkOrm\Tests\Integration;

use think\Db;
use ThinkOrm\Tests\Fixture\Log;
use ThinkOrm\Tests\IntegrationTestCase;

/**
 * @group integration
 *
 * 复合主键测试：logs 表 PK = (log_date, seq)
 */
class ComplexPkTest extends IntegrationTestCase
{
    public function testInsert()
    {
        $n = Db::name('logs')->insert([
            'log_date' => '2024-01-01', 'seq' => 1, 'level' => 'INFO', 'message' => 'hello',
        ]);
        $this->assertSame(1, $n);
        $this->assertSame(1, Db::name('logs')->count());
    }

    public function testFindByDoubleKey()
    {
        Db::name('logs')->insert([
            'log_date' => '2024-01-01', 'seq' => 1, 'level' => 'INFO', 'message' => 'a',
        ]);
        Db::name('logs')->insert([
            'log_date' => '2024-01-01', 'seq' => 2, 'level' => 'WARN', 'message' => 'b',
        ]);
        Db::name('logs')->insert([
            'log_date' => '2024-01-02', 'seq' => 1, 'level' => 'INFO', 'message' => 'c',
        ]);

        $row = Db::name('logs')
            ->where('log_date', '2024-01-01')
            ->where('seq', 2)
            ->find();
        $this->assertSame('WARN', $row['level']);
    }

    public function testUpdateByDoubleKey()
    {
        Db::name('logs')->insert([
            'log_date' => '2024-01-01', 'seq' => 1, 'level' => 'INFO', 'message' => 'a',
        ]);
        $n = Db::name('logs')
            ->where('log_date', '2024-01-01')
            ->where('seq', 1)
            ->update(['level' => 'ERROR']);
        $this->assertSame(1, $n);
    }

    public function testDeleteByDoubleKey()
    {
        Db::name('logs')->insert([
            'log_date' => '2024-01-01', 'seq' => 1, 'level' => 'INFO', 'message' => 'a',
        ]);
        Db::name('logs')->insert([
            'log_date' => '2024-01-01', 'seq' => 2, 'level' => 'INFO', 'message' => 'b',
        ]);
        $n = Db::name('logs')
            ->where('log_date', '2024-01-01')
            ->where('seq', 1)
            ->delete();
        $this->assertSame(1, $n);
        $this->assertSame(1, Db::name('logs')->count());
    }
}
