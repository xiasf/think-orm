<?php

namespace ThinkOrm\Tests\Integration;

use think\Db;
use think\Paginator;
use think\Request;
use ThinkOrm\Tests\IntegrationTestCase;

/**
 * @group integration
 */
class PaginatorTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // 默认 Request 桩 param() 返回 null，page 默认 1
        Request::setInstance(new Request());
    }

    public function testSimplePaginate()
    {
        for ($i = 1; $i <= 23; $i++) {
            $this->seedUser(['age' => $i]);
        }

        $pager = Db::name('users')->order('id')->paginate(10);
        $this->assertInstanceOf(Paginator::class, $pager);
        $this->assertSame(10, count($pager->items()));
        $this->assertSame(23, $pager->total());
        $this->assertSame(3, $pager->lastPage());
        $this->assertSame(1, $pager->currentPage());
    }

    public function testSecondPage()
    {
        for ($i = 1; $i <= 23; $i++) {
            $this->seedUser(['age' => $i]);
        }
        // 显式传 page 参数取第 2 页
        $pager = Db::name('users')->order('id')->paginate(10, false, ['page' => 2]);
        $this->assertSame(2, $pager->currentPage());
        $this->assertCount(10, $pager->items());
    }

    public function testCustomPageVar()
    {
        for ($i = 1; $i <= 15; $i++) {
            $this->seedUser();
        }
        // 使用 page() 直接指定
        $pager = Db::name('users')->order('id')->paginate(5, false, ['page' => 2]);
        $this->assertSame(2, $pager->currentPage());
        $this->assertCount(5, $pager->items());
    }

    public function testPaginatorToArray()
    {
        for ($i = 1; $i <= 12; $i++) {
            $this->seedUser();
        }
        $pager = Db::name('users')->order('id')->paginate(5);
        $arr = $pager->toArray();
        $this->assertArrayHasKey('total', $arr);
        $this->assertArrayHasKey('per_page', $arr);
        $this->assertArrayHasKey('current_page', $arr);
        $this->assertArrayHasKey('last_page', $arr);
        $this->assertArrayHasKey('data', $arr);
        $this->assertSame(12, $arr['total']);
        $this->assertSame(5, $arr['per_page']);
    }

    public function testEmptyPaginator()
    {
        $pager = Db::name('users')->order('id')->paginate(10);
        $this->assertSame(0, $pager->total());
        $this->assertCount(0, $pager->items());
    }
}
