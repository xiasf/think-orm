<?php

namespace ThinkOrm\Tests\Integration;

use think\Db;
use ThinkOrm\Tests\Fixture\Comment;
use ThinkOrm\Tests\Fixture\Post;
use ThinkOrm\Tests\Fixture\User;
use ThinkOrm\Tests\IntegrationTestCase;

/**
 * @group integration
 *
 * User -> posts -> comments：User 通过 Post 远程关联 Comment
 */
class RelationHasManyThroughTest extends IntegrationTestCase
{
    public function testThroughRelationReturnsRelatedModels()
    {
        $uid = $this->seedUser(['name' => 'author']);
        $pid1 = Db::name('posts')->insertGetId(['user_id' => $uid, 'title' => 'p1']);
        $pid2 = Db::name('posts')->insertGetId(['user_id' => $uid, 'title' => 'p2']);

        Db::name('comments')->insert(['post_id' => $pid1, 'commentable_type' => 'post', 'commentable_id' => $pid1, 'author' => 'a1', 'body' => 'c1']);
        Db::name('comments')->insert(['post_id' => $pid1, 'commentable_type' => 'post', 'commentable_id' => $pid1, 'author' => 'a2', 'body' => 'c2']);
        Db::name('comments')->insert(['post_id' => $pid2, 'commentable_type' => 'post', 'commentable_id' => $pid2, 'author' => 'a3', 'body' => 'c3']);

        $user = User::get($uid);
        $comments = $user->comments;
        $this->assertCount(3, $comments);
    }

    public function testThroughExcludesOtherUsers()
    {
        $u1 = $this->seedUser();
        $u2 = $this->seedUser();
        $p1 = Db::name('posts')->insertGetId(['user_id' => $u1, 'title' => 'p1']);
        $p2 = Db::name('posts')->insertGetId(['user_id' => $u2, 'title' => 'p2']);
        Db::name('comments')->insert(['post_id' => $p1, 'commentable_type' => 'post', 'commentable_id' => $p1, 'author' => 'a1', 'body' => 'x']);
        Db::name('comments')->insert(['post_id' => $p2, 'commentable_type' => 'post', 'commentable_id' => $p2, 'author' => 'a2', 'body' => 'y']);

        $user = User::get($u1);
        $this->assertCount(1, $user->comments);
    }
}
