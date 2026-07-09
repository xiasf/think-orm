<?php

namespace ThinkOrm\Tests\Integration;

use think\Db;
use ThinkOrm\Tests\Fixture\Post;
use ThinkOrm\Tests\Fixture\User;
use ThinkOrm\Tests\IntegrationTestCase;

/**
 * @group integration
 */
class RelationHasManyTest extends IntegrationTestCase
{
    public function testHasManyReturnsCollection()
    {
        $uid = $this->seedUser(['name' => 'u']);
        Db::name('posts')->insert(['user_id' => $uid, 'title' => 'p1']);
        Db::name('posts')->insert(['user_id' => $uid, 'title' => 'p2']);
        Db::name('posts')->insert(['user_id' => $uid, 'title' => 'p3']);

        $user = User::get($uid);
        $posts = $user->posts;
        $this->assertCount(3, $posts);
    }

    public function testHasManyEmpty()
    {
        $uid = $this->seedUser();
        $user = User::get($uid);
        $this->assertCount(0, $user->posts);
    }

    public function testHasManyCount()
    {
        $uid = $this->seedUser();
        Db::name('posts')->insert(['user_id' => $uid, 'title' => 'a']);
        Db::name('posts')->insert(['user_id' => $uid, 'title' => 'b']);
        $user = User::get($uid);
        $this->assertSame(2, $user->posts()->count());
    }

    public function testEagerLoadHasMany()
    {
        $u1 = $this->seedUser();
        $u2 = $this->seedUser();
        Db::name('posts')->insert(['user_id' => $u1, 'title' => 'a']);
        Db::name('posts')->insert(['user_id' => $u1, 'title' => 'b']);
        Db::name('posts')->insert(['user_id' => $u2, 'title' => 'c']);

        $users = User::with('posts')->select();
        foreach ($users as $u) {
            if ($u->id == $u1) {
                $this->assertCount(2, $u->posts);
            } else {
                $this->assertCount(1, $u->posts);
            }
        }
    }
}
