<?php

namespace ThinkOrm\Tests\Fixture;

use think\Model;

class Log extends Model
{
    protected $table = 'logs';
    protected $pk = ['log_date', 'seq'];
    protected $autoWriteTimestamp = false;
}
