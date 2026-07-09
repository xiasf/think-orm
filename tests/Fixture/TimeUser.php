<?php

namespace ThinkOrm\Tests\Fixture;

use think\Model;

class TimeUser extends Model
{
    protected $table = 'users';
    protected $autoWriteTimestamp = 'datetime';
    protected $dateFormat = 'Y-m-d H:i:s';
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';
}
