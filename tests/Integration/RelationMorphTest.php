<?php

namespace ThinkOrm\Tests\Integration;

use think\Db;
use ThinkOrm\Tests\Fixture\Comment;
use ThinkOrm\Tests\Fixture\Image;
use ThinkOrm\Tests\Fixture\Post;
use ThinkOrm\Tests\Fixture\User;
use ThinkOrm\Tests\IntegrationTestCase;

/**
 * @group integration
 */
class RelationMorphTest extends IntegrationTestCase
{
    public function testMorphManyReturnsRelated()
    {
        $pid = Db::name('posts')->insertGetId(['user_id' => 1, 'title' => 'p']);
        Db::name('comments')->insert(['post_id' => $pid, 'commentable_type' => Post::class, 'commentable_id' => $pid, 'author' => 'a', 'body' => 'c1']);
        Db::name('comments')->insert(['post_id' => $pid, 'commentable_type' => Post::class, 'commentable_id' => $pid, 'author' => 'b', 'body' => 'c2']);

        $post = Post::get($pid);
        $comments = $post->comments;
        $this->assertCount(2, $comments);
    }

    public function testMorphManyExcludesOtherTypes()
    {
        $pid = Db::name('posts')->insertGetId(['user_id' => 1, 'title' => 'p']);
        Db::name('comments')->insert(['post_id' => $pid, 'commentable_type' => Post::class, 'commentable_id' => $pid, 'author' => 'a', 'body' => 'c1']);
        Db::name('comments')->insert(['post_id' => $pid, 'commentable_type' => 'Other', 'commentable_id' => $pid, 'author' => 'b', 'body' => 'c2']);

        $post = Post::get($pid);
        $comments = $post->comments;
        $this->assertCount(1, $comments);
    }

    public function testMorphToReturnsParent()
    {
        $pid = Db::name('posts')->insertGetId(['user_id' => 1, 'title' => 'p']);
        $cid = Db::name('comments')->insertGetId(['post_id' => $pid, 'commentable_type' => Post::class, 'commentable_id' => $pid, 'author' => 'a', 'body' => 'c']);

        $comment = Comment::get($cid);
        $parent = $comment->commentable;
        $this->assertInstanceOf(Post::class, $parent);
        $this->assertSame('p', $parent->title);
    }
}
