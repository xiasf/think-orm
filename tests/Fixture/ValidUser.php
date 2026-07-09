<?php

namespace ThinkOrm\Tests\Fixture;

use think\Model;

class ValidUser extends Model
{
    protected $table = 'users';
    protected $autoWriteTimestamp = false;

    protected $validate = [
        'rule' => [
            'name'  => 'require|max:30',
            'email' => 'require|email',
            'age'   => 'integer|>=:0',
        ],
        'msg' => [],
    ];

    protected $failException = true;
}
