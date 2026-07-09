<?php

namespace app\di\validate;

use think\Validate;

class Smartpark extends Validate
{
    protected $rule = [
        'name'   => 'require|max:100',
        'number' => 'max:50',
    ];

    protected $message = [
        'name.require' => '园区名称必填',
        'name.max'     => '园区名称不能超过 100 字符',
        'number.max'   => '园区编号不能超过 50 字符',
    ];

    protected $scene = [
        'add'  => ['name', 'number'],
        'edit' => ['name' => 'max:100', 'number'],
    ];
}
