<?php

namespace ThinkOrm\Tests\Fixture;

use think\Model;
use think\traits\model\SoftDelete;

class User extends Model
{
    protected $table = 'users';
    protected $autoWriteTimestamp = false;

    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    public function profile()
    {
        return $this->hasOne(Profile::class);
    }

    public function comments()
    {
        // User 通过 posts 表远程关联 comments（posts.user_id → comments.post_id）
        return $this->hasManyThrough(Comment::class, Post::class);
    }
}
