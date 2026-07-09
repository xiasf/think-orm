<?php

namespace ThinkOrm\Tests\Integration;

use think\Db;
use ThinkOrm\Tests\Fixture\Post;
use ThinkOrm\Tests\Fixture\User;
use ThinkOrm\Tests\IntegrationTestCase;

/**
 * @group integration
 */
class RelationHasOneTest extends IntegrationTestCase
{
    public function testHasOneReturnsRelated()
    {
        $uid = $this->seedUser(['name' => 'u1']);
        // 在 posts 表中插一条作为"profile"，外键 user_id
        Db::name('posts')->insert(['user_id' => $uid, 'title' => 'first']);

        $user = User::get($uid);
        $profile = $user->profile;
        $this->assertNotNull($profile);
        $this->assertSame('first', $profile->title);
    }

    public function testHasOneReturnsNullWhenMissing()
    {
        $uid = $this->seedUser();
        $user = User::get($uid);
        $this->assertNull($user->profile);
    }

    public function testEagerLoad()
    {
        $u1 = $this->seedUser(['name' => 'a']);
        $u2 = $this->seedUser(['name' => 'b']);
        Db::name('posts')->insert(['user_id' => $u1, 'title' => 'p1']);
        Db::name('posts')->insert(['user_id' => $u2, 'title' => 'p2']);

        $users = User::with('profile')->select();
        $this->assertCount(2, $users);
        $titles = [];
        foreach ($users as $u) {
            $titles[] = $u->profile ? $u->profile->title : null;
        }
        sort($titles);
        $this->assertSame(['p1', 'p2'], $titles);
    }
}
