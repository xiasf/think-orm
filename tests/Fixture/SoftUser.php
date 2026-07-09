<?php

namespace ThinkOrm\Tests\Fixture;

use think\Model;
use think\traits\model\SoftDelete;

class SoftUser extends Model
{
    use SoftDelete;

    protected $table = 'users';
    protected $deleteTime = 'delete_time';
    protected $autoWriteTimestamp = false;
}
