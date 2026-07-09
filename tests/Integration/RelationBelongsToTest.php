<?php

namespace ThinkOrm\Tests\Integration;

use think\Db;
use ThinkOrm\Tests\Fixture\Post;
use ThinkOrm\Tests\Fixture\User;
use ThinkOrm\Tests\IntegrationTestCase;

/**
 * @group integration
 */
class RelationBelongsToTest extends IntegrationTestCase
{
    public function testBelongsToReturnsParent()
    {
        $uid = $this->seedUser(['name' => 'parent']);
        $pid = Db::name('posts')->insertGetId(['user_id' => $uid, 'title' => 'p']);

        $post = Post::get($pid);
        $user = $post->user;
        $this->assertNotNull($user);
        $this->assertSame('parent', $user->name);
    }

    public function testBelongsToReturnsNullWhenMissing()
    {
        $pid = Db::name('posts')->insertGetId(['user_id' => 999999, 'title' => 'p']);
        $post = Post::get($pid);
        $this->assertNull($post->user);
    }

    public function testEagerLoadBelongsTo()
    {
        $u1 = $this->seedUser(['name' => 'a']);
        $u2 = $this->seedUser(['name' => 'b']);
        Db::name('posts')->insert(['user_id' => $u1, 'title' => 'p1']);
        Db::name('posts')->insert(['user_id' => $u2, 'title' => 'p2']);

        $posts = Post::with('user')->select();
        $this->assertCount(2, $posts);
        $names = [];
        foreach ($posts as $p) {
            $names[] = $p->user->name;
        }
        sort($names);
        $this->assertSame(['a', 'b'], $names);
    }
}
