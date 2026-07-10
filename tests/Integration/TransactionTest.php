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

    /**
     * transaction() 助手函数（yf common.php 兼容语义）
     * 成功：返回闭包结果，errMsg 空字符串，errCode 0
     */
    public function testHelperTransactionSuccess()
    {
        $result = transaction(function () {
            Db::name('users')->insert(['name' => 'h1', 'email' => 'h1@x', 'age' => 1]);
            Db::name('users')->insert(['name' => 'h2', 'email' => 'h2@x', 'age' => 2]);
            return 'helper-result';
        }, $errMsg, $errCode, $exception);

        $this->assertSame('helper-result', $result);
        $this->assertSame('', $errMsg);
        $this->assertSame(0, $errCode);
        $this->assertNull($exception);
        $this->assertSame(2, Db::name('users')->count());
    }

    /**
     * transaction() 助手函数：失败时不抛出，异常消息通过引用传出
     * 失败返回 false（yf 约定），事务自动回滚
     */
    public function testHelperTransactionFailureReturnsFalseWithErrMsg()
    {
        $result = transaction(function () {
            Db::name('users')->insert(['name' => 'will-fail', 'email' => 'f@x', 'age' => 1]);
            throw new \RuntimeException('business exploded');
        }, $errMsg, $errCode, $exception);

        $this->assertFalse($result);
        $this->assertSame('business exploded', $errMsg);
        $this->assertSame(0, Db::name('users')->count(), '事务应回滚');
        $this->assertInstanceOf(\Throwable::class, $exception);
        $this->assertInstanceOf(\RuntimeException::class, $exception);
    }

    /**
     * 空异常消息兜底为 'unknown error'
     */
    public function testHelperTransactionEmptyMessageFallback()
    {
        $result = transaction(function () {
            throw new \Exception('');   // 空消息
        }, $errMsg);

        $this->assertFalse($result);
        $this->assertSame('unknown error', $errMsg);
    }

    /**
     * errCode 取自异常 getCode()
     */
    public function testHelperTransactionErrCodeFromException()
    {
        $result = transaction(function () {
            throw new \RuntimeException('with code', 5001);
        }, $errMsg, $errCode);

        $this->assertFalse($result);
        $this->assertSame(5001, $errCode);
    }
}
