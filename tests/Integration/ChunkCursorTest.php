<?php

namespace ThinkOrm\Tests\Integration;

use think\Db;
use ThinkOrm\Tests\IntegrationTestCase;

/**
 * @group integration
 */
class ChunkCursorTest extends IntegrationTestCase
{
    public function testChunk()
    {
        for ($i = 1; $i <= 25; $i++) $this->seedUser(['age' => $i]);

        $seen = 0;
        $ok = Db::name('users')->order('age')->chunk(10, function ($rows) use (&$seen) {
            $seen += count($rows);
        }, 'age');
        $this->assertTrue($ok);
        $this->assertSame(25, $seen);
    }

    public function testChunkStopsWhenCallbackReturnsFalse()
    {
        for ($i = 1; $i <= 30; $i++) $this->seedUser(['age' => $i]);

        $seen = 0;
        Db::name('users')->order('age')->chunk(10, function ($rows) use (&$seen) {
            $seen += count($rows);
            return false; // 第一次返回 false 中止
        }, 'age');
        $this->assertSame(10, $seen);
    }

    public function testEach()
    {
        // TP 5.0.24 没有 each()，用 chunk 模拟
        for ($i = 1; $i <= 5; $i++) $this->seedUser(['age' => $i]);

        $ids = [];
        Db::name('users')->order('age')->chunk(2, function ($rows) use (&$ids) {
            foreach ($rows as $r) $ids[] = $r['id'];
        });
        $this->assertCount(5, $ids);
    }

    public function testChunkedByColumn()
    {
        // chunkById 等同 chunk，TP 5.0.24 用 chunk($size, callable, $column)
        for ($i = 1; $i <= 25; $i++) $this->seedUser(['age' => $i]);
        $seen = 0;
        $ok = Db::name('users')->order('age')->chunk(10, function ($rows) use (&$seen) {
            $seen += count($rows);
        }, 'age');
        $this->assertTrue($ok);
        $this->assertSame(25, $seen);
    }
}
