<?php

namespace ThinkOrm\Tests\Fixture;

use think\Model;

class Image extends Model
{
    protected $table = 'images';

    public function imageable()
    {
        return $this->morphTo('imageable');
    }
}
