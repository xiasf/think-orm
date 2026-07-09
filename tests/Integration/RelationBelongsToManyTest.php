<?php

namespace ThinkOrm\Tests\Integration;

use think\Db;
use ThinkOrm\Tests\Fixture\Post;
use ThinkOrm\Tests\Fixture\Tag;
use ThinkOrm\Tests\IntegrationTestCase;

/**
 * @group integration
 */
class RelationBelongsToManyTest extends IntegrationTestCase
{
    public function testAttach()
    {
        $pid = Db::name('posts')->insertGetId(['user_id' => 1, 'title' => 'p']);
        $tid1 = Db::name('tags')->insertGetId(['name' => 'php']);
        $tid2 = Db::name('tags')->insertGetId(['name' => 'orm']);

        $post = Post::get($pid);
        $post->tags()->attach([$tid1, $tid2]);

        $this->assertSame(2, Db::name('posts_tags')->where('post_id', $pid)->count());
    }

    public function testRelatedTagsLoaded()
    {
        $pid = Db::name('posts')->insertGetId(['user_id' => 1, 'title' => 'p']);
        $tid = Db::name('tags')->insertGetId(['name' => 'php']);
        Db::name('posts_tags')->insert(['post_id' => $pid, 'tag_id' => $tid]);

        $post = Post::get($pid);
        $tags = $post->tags;
        $this->assertCount(1, $tags);
        $this->assertSame('php', $tags[0]->name);
    }

    public function testDetach()
    {
        $pid = Db::name('posts')->insertGetId(['user_id' => 1, 'title' => 'p']);
        $tid1 = Db::name('tags')->insertGetId(['name' => 'a']);
        $tid2 = Db::name('tags')->insertGetId(['name' => 'b']);
        Db::name('posts_tags')->insert(['post_id' => $pid, 'tag_id' => $tid1]);
        Db::name('posts_tags')->insert(['post_id' => $pid, 'tag_id' => $tid2]);

        $post = Post::get($pid);
        $post->tags()->detach($tid1);
        $this->assertSame(1, Db::name('posts_tags')->where('post_id', $pid)->count());
    }

    public function testDetachAll()
    {
        $pid = Db::name('posts')->insertGetId(['user_id' => 1, 'title' => 'p']);
        $tid1 = Db::name('tags')->insertGetId(['name' => 'a']);
        $tid2 = Db::name('tags')->insertGetId(['name' => 'b']);
        Db::name('posts_tags')->insert(['post_id' => $pid, 'tag_id' => $tid1]);
        Db::name('posts_tags')->insert(['post_id' => $pid, 'tag_id' => $tid2]);

        $post = Post::get($pid);
        $post->tags()->detach();
        $this->assertSame(0, Db::name('posts_tags')->where('post_id', $pid)->count());
    }

    public function testEagerLoad()
    {
        $pid = Db::name('posts')->insertGetId(['user_id' => 1, 'title' => 'p']);
        $tid1 = Db::name('tags')->insertGetId(['name' => 'a']);
        $tid2 = Db::name('tags')->insertGetId(['name' => 'b']);
        Db::name('posts_tags')->insert(['post_id' => $pid, 'tag_id' => $tid1]);
        Db::name('posts_tags')->insert(['post_id' => $pid, 'tag_id' => $tid2]);

        $posts = Post::with('tags')->select();
        $this->assertCount(1, $posts);
        $this->assertCount(2, $posts[0]->tags);
    }

    public function testReverseRelation()
    {
        $pid = Db::name('posts')->insertGetId(['user_id' => 1, 'title' => 'p']);
        $tid = Db::name('tags')->insertGetId(['name' => 'a']);
        Db::name('posts_tags')->insert(['post_id' => $pid, 'tag_id' => $tid]);

        $tag = Tag::get($tid);
        $posts = $tag->posts;
        $this->assertCount(1, $posts);
        $this->assertSame('p', $posts[0]->title);
    }
}
