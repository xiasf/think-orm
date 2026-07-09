<?php

namespace ThinkOrm\Tests\Fixture;

use think\Model;

class Post extends Model
{
    protected $table = 'posts';
    protected $autoWriteTimestamp = false;

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'posts_tags');
    }
}
