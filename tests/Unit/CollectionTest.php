<?php

namespace ThinkOrm\Tests\Unit;

use think\Collection;
use ThinkOrm\Tests\UnitTestCase;

/**
 * @group unit
 */
class CollectionTest extends UnitTestCase
{
    public function testBasicArrayAccess()
    {
        $c = new Collection([1, 2, 3]);
        $this->assertCount(3, $c);
        $this->assertSame(2, $c[1]);
    }

    public function testMake()
    {
        $c = Collection::make(['a', 'b']);
        $this->assertInstanceOf(Collection::class, $c);
        $this->assertSame(['a', 'b'], $c->toArray());
    }

    public function testIsEmpty()
    {
        $this->assertTrue((new Collection([]))->isEmpty());
        $this->assertFalse((new Collection([1]))->isEmpty());
    }

    public function testAll()
    {
        $c = new Collection([1, 2, 3]);
        $this->assertSame([1, 2, 3], $c->all());
    }

    public function testKeysAndValues()
    {
        $c = new Collection(['a' => 1, 'b' => 2]);
        $this->assertSame(['a', 'b'], $c->keys()->toArray());
        $this->assertSame([1, 2], $c->values()->toArray());
    }

    public function testFlip()
    {
        $c = new Collection(['a' => 1, 'b' => 2]);
        $this->assertSame([1 => 'a', 2 => 'b'], $c->flip()->toArray());
    }

    public function testMerge()
    {
        $c = new Collection([1, 2]);
        $merged = $c->merge([3, 4]);
        $this->assertSame([1, 2, 3, 4], $merged->toArray());
    }

    public function testEach()
    {
        $c = new Collection([1, 2, 3]);
        $out = [];
        $c->each(function ($v) use (&$out) { $out[] = $v * 2; });
        $this->assertSame([2, 4, 6], $out);
    }

    public function testFilter()
    {
        $c = new Collection([1, 2, 3, 4]);
        $filtered = $c->filter(function ($v) { return $v % 2 === 0; });
        $this->assertSame([1 => 2, 3 => 4], $filtered->toArray());
    }

    public function testColumn()
    {
        $c = new Collection([
            ['id' => 1, 'name' => 'a'],
            ['id' => 2, 'name' => 'b'],
        ]);
        $this->assertSame([1, 2], $c->column('id'));
        $this->assertSame(['a' => 1, 'b' => 2], $c->column('id', 'name'));
    }

    public function testReduce()
    {
        $c = new Collection([1, 2, 3, 4]);
        $sum = $c->reduce(function ($carry, $item) { return $carry + $item; }, 0);
        $this->assertSame(10, $sum);
    }

    public function testSlice()
    {
        $c = new Collection([1, 2, 3, 4, 5]);
        $this->assertSame([3, 4, 5], $c->slice(2)->toArray());
        $this->assertSame([3, 4], $c->slice(2, 2)->toArray());
    }

    public function testChunk()
    {
        $c = new Collection([1, 2, 3, 4]);
        $chunks = $c->chunk(2);
        $this->assertSame([[1, 2], [3, 4]], $chunks->toArray());
    }

    public function testPushAndPop()
    {
        $c = new Collection([1, 2]);
        $c[] = 3;
        $this->assertSame([1, 2, 3], $c->toArray());
    }

    public function testReverse()
    {
        $c = new Collection([1, 2, 3]);
        $this->assertSame([3, 2, 1], $c->reverse()->toArray());
    }

    public function testShiftAndPop()
    {
        $c = new Collection([1, 2, 3]);
        $this->assertSame(1, $c->shift());
        $this->assertSame(3, $c->pop());
    }

    public function testToJson()
    {
        $c = new Collection(['a' => 1, 'b' => 2]);
        $json = $c->toJson();
        $this->assertSame(['a' => 1, 'b' => 2], json_decode($json, true));
    }

    public function testJsonSerializable()
    {
        $c = new Collection([1, 2, 3]);
        $this->assertSame('[1,2,3]', json_encode($c));
    }

    public function testCountable()
    {
        $c = new Collection([1, 2, 3]);
        $this->assertCount(3, $c);
    }

    public function testIterator()
    {
        $c = new Collection([10, 20, 30]);
        $out = [];
        foreach ($c as $v) $out[] = $v;
        $this->assertSame([10, 20, 30], $out);
    }
}
