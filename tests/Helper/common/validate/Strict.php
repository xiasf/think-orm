<?php

namespace ThinkOrm\Tests\Helper\common\validate;

use think\Validate;

class Strict extends Validate
{
    protected $rule = [
        'name' => 'require',
        'age'  => 'integer|>=:0',
    ];
}
