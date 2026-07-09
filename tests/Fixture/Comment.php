<?php

namespace ThinkOrm\Tests\Fixture;

use think\Model;

class Comment extends Model
{
    protected $table = 'comments';
    protected $autoWriteTimestamp = false;

    public function commentable()
    {
        return $this->morphTo('commentable');
    }
}
