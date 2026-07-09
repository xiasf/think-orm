<?php

namespace ThinkOrm\Tests\Integration;

use think\Db;
use ThinkOrm\Tests\Fixture\AccessorUser;
use ThinkOrm\Tests\IntegrationTestCase;

/**
 * @group integration
 */
class ModelAccessorMutatorTest extends IntegrationTestCase
{
    public function testGetterAccessor()
    {
        $id = $this->seedUser(['name' => 'alice']);
        $user = AccessorUser::get($id);
        $this->assertSame('ALICE', $user->name); // 经过 getNameAttr
    }

    public function testSetterMutator()
    {
        $user = AccessorUser::create(['name' => 'ALICE', 'email' => 'a@x', 'age' => 1]);
        $raw = Db::name('users')->where('id', $user->id)->value('name');
        $this->assertSame('alice', $raw); // 写入时小写
    }

    public function testVirtualAccessor()
    {
        $id = $this->seedUser(['name' => 'alice', 'email' => 'a@x']);
        $user = AccessorUser::get($id);
        $this->assertSame('ALICE <a@x>', $user->full_name);
    }

    public function testAppendVirtualAttr()
    {
        $id = $this->seedUser(['name' => 'bob', 'email' => 'b@x']);
        $user = AccessorUser::get($id);
        $arr = $user->append(['full_name'])->toArray();
        $this->assertArrayHasKey('full_name', $arr);
        $this->assertSame('BOB <b@x>', $arr['full_name']);
    }

    public function testGetDataReturnsRaw()
    {
        $id = $this->seedUser(['name' => 'Alice']);
        $user = AccessorUser::get($id);
        // getData 绕过获取器，返回原始值
        $this->assertSame('Alice', $user->getData('name'));
        // getAttr 走获取器
        $this->assertSame('ALICE', $user->getAttr('name'));
    }
}
