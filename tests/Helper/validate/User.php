<?php

namespace ThinkOrm\Tests\Helper\validate;

use think\Validate;

class User extends Validate
{
    protected $rule = [
        'name'  => 'require|max:30',
        'email' => 'require|email',
        'age'   => 'integer|>=:0|<=:150',
    ];

    protected $message = [
        'name.require' => '名字必须填',
        'name.max'     => '名字不能超过 30 字符',
        'email.email'  => '邮箱格式不对',
        'age.integer'  => '年龄必须是整数',
        'age.>='       => '年龄不能小于 0',
        'age.<='       => '年龄不能超过 150',
    ];

    protected $scene = [
        'create' => ['name', 'email', 'age'],
        'update' => ['name', 'age'],
        'login'  => ['email'],
    ];
}
