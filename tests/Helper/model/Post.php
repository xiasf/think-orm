<?php

namespace ThinkOrm\Tests\Helper\model;

use think\Model;

class Post extends Model
{
    protected $table = 'posts';
    protected $autoWriteTimestamp = false;
}
