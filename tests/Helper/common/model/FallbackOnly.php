<?php

namespace ThinkOrm\Tests\Helper\common\model;

use think\Model;

class FallbackOnly extends Model
{
    protected $table = 'fallback_only';
    protected $autoWriteTimestamp = false;
}
