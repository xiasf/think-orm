<?php

namespace ThinkOrm\Tests\Fixture;

use think\Model;

class Profile extends Model
{
    protected $table = 'posts';
    protected $autoWriteTimestamp = false;

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
