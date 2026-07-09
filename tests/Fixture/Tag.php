<?php

namespace ThinkOrm\Tests\Fixture;

use think\Model;

class Tag extends Model
{
    protected $table = 'tags';

    public function posts()
    {
        return $this->belongsToMany(Post::class, 'posts_tags');
    }
}
