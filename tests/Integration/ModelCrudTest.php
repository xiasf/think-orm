<?php

namespace ThinkOrm\Tests\Integration;

use think\Db;
use ThinkOrm\Tests\Fixture\User;
use ThinkOrm\Tests\IntegrationTestCase;

/**
 * @group integration
 */
class ModelCrudTest extends IntegrationTestCase
{
    public function testCreateReturnsModelInstance()
    {
        $user = User::create(['name' => 'alice', 'email' => 'a@x', 'age' => 20]);
        $this->assertInstanceOf(User::class, $user);
        $this->assertNotEmpty($user->id);
        $this->assertSame('alice', $user->name);

        $row = Db::name('users')->where('id', $user->id)->find();
        $this->assertSame('alice', $row['name']);
    }

    public function testStaticGet()
    {
        $id = $this->seedUser(['name' => 'bob']);
        $user = User::get($id);
        $this->assertInstanceOf(User::class, $user);
        $this->assertSame('bob', $user->name);
    }

    public function testStaticAll()
    {
        $this->seedUser(['name' => 'a']);
        $this->seedUser(['name' => 'b']);
        $all = User::all();
        $this->assertCount(2, $all);
    }

    public function testStaticAllWithIds()
    {
        $id1 = $this->seedUser(['name' => 'a']);
        $id2 = $this->seedUser(['name' => 'b']);
        $id3 = $this->seedUser(['name' => 'c']);
        $subset = User::all([$id1, $id3]);
        $this->assertCount(2, $subset);
    }

    public function testSaveUpdate()
    {
        $user = User::create(['name' => 'op', 'email' => 'o@x', 'age' => 1]);
        $user->age = 99;
        $user->save();

        $row = Db::name('users')->where('id', $user->id)->find();
        $this->assertSame('99', (string) $row['age']);
    }

    public function testStaticUpdate()
    {
        $id = $this->seedUser(['name' => 'u', 'age' => 1]);
        User::update(['id' => $id, 'age' => 50]);
        $this->assertSame('50', (string) Db::name('users')->where('id', $id)->value('age'));
    }

    public function testDeleteAndDestroy()
    {
        $id = $this->seedUser(['name' => 'u']);

        $n = User::destroy($id);
        $this->assertSame(1, $n);
        $this->assertSame(0, Db::name('users')->count());
    }

    public function testInstanceDelete()
    {
        $id = $this->seedUser(['name' => 'u']);
        $user = User::get($id);
        $user->delete();
        $this->assertNull(Db::name('users')->where('id', $id)->find());
    }

    public function testSaveAll()
    {
        $users = [
            new User(['name' => 'a', 'email' => 'a@x', 'age' => 1]),
            new User(['name' => 'b', 'email' => 'b@x', 'age' => 2]),
            new User(['name' => 'c', 'email' => 'c@x', 'age' => 3]),
        ];
        $set = (new User())->saveAll($users);
        $this->assertCount(3, $set);
        $this->assertSame(3, Db::name('users')->count());
    }

    public function testWhereOnModelReturnsQuery()
    {
        $this->seedUser(['age' => 10]);
        $this->seedUser(['age' => 20]);
        $rows = User::where('age', '>', 10)->select();
        $this->assertCount(1, $rows);
    }

    public function testFindReturnsModelOrNull()
    {
        $id = $this->seedUser(['name' => 'foo']);
        $found = User::where('id', $id)->find();
        $this->assertInstanceOf(User::class, $found);
        $this->assertNull(User::where('id', 99999)->find());
    }

    public function testValueAndColumn()
    {
        $this->seedUser(['name' => 'a', 'email' => 'a@x']);
        $this->seedUser(['name' => 'b', 'email' => 'b@x']);
        $this->assertSame('a', User::order('id')->value('name'));
        $emails = User::order('id')->column('email');
        $this->assertSame(['a@x', 'b@x'], $emails);
    }

    public function testSaveOnlyOnDirty()
    {
        $user = User::create(['name' => 'u', 'email' => 'u@x', 'age' => 1]);
        $user->name = 'u'; // 未变更
        $user->age = 1;    // 未变更
        $affected = $user->save();
        $this->assertSame(0, $affected);
    }
}
