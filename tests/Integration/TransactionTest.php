<?php

namespace ThinkOrm\Tests\Integration;

use think\Db;
use think\Exception;
use ThinkOrm\Tests\IntegrationTestCase;

/**
 * @group integration
 */
class TransactionTest extends IntegrationTestCase
{
    public function testCommit()
    {
        Db::startTrans();
        try {
            Db::name('users')->insert(['name' => 't', 'email' => 't@x', 'age' => 1]);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
        $this->assertSame(1, Db::name('users')->count());
    }

    public function testRollback()
    {
        Db::startTrans();
        try {
            Db::name('users')->insert(['name' => 't', 'email' => 't@x', 'age' => 1]);
            throw new \RuntimeException('boom');
        } catch (\Throwable $e) {
            Db::rollback();
        }
        $this->assertSame(0, Db::name('users')->count());
    }

    public function testTransactionClosureSuccess()
    {
        $result = Db::transaction(function () {
            Db::name('users')->insert(['name' => 'a', 'email' => 'a@x', 'age' => 1]);
            Db::name('users')->insert(['name' => 'b', 'email' => 'b@x', 'age' => 2]);
            return 'done';
        });
        $this->assertSame('done', $result);
        $this->assertSame(2, Db::name('users')->count());
    }

    public function testTransactionClosureFailure()
    {
        try {
            Db::transaction(function () {
                Db::name('users')->insert(['name' => 'a', 'email' => 'a@x', 'age' => 1]);
                throw new \RuntimeException('boom');
            });
            $this->fail('expected exception');
        } catch (\RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }
        $this->assertSame(0, Db::name('users')->count());
    }

    public function testNestedTransaction()
    {
        Db::transaction(function () {
            Db::name('users')->insert(['name' => 'outer', 'email' => 'o@x', 'age' => 1]);
            Db::transaction(function () {
                Db::name('users')->insert(['name' => 'inner', 'email' => 'i@x', 'age' => 2]);
            });
        });
        $this->assertSame(2, Db::name('users')->count());
    }

    public function testNestedTransactionRollbackInner()
    {
        try {
            Db::transaction(function () {
                Db::name('users')->insert(['name' => 'outer', 'email' => 'o@x', 'age' => 1]);
                Db::transaction(function () {
                    Db::name('users')->insert(['name' => 'inner', 'email' => 'i@x', 'age' => 2]);
                    throw new \RuntimeException('inner boom');
                });
            });
            $this->fail('expected');
        } catch (\RuntimeException $e) {
            // 嵌套事务一起回滚
        }
        $this->assertSame(0, Db::name('users')->count());
    }
}
