<?php

namespace app\di\validate;

use think\Validate;

class Notice extends Validate
{
    protected $rule = [
        'smartpark_id' => 'require|integer|>:0',
        'name'         => 'require|max:100',
        'channels'     => 'require',
        'status'       => 'in:0,1,2',
    ];

    protected $message = [
        'smartpark_id.require' => '园区ID必填',
        'smartpark_id.integer' => '园区ID必须是整数',
        'smartpark_id.>'       => '园区ID必须大于 0',
        'name.require'         => '通知名称必填',
        'name.max'             => '通知名称不能超过 100 字符',
        'channels.require'     => '通道必填',
        'status.in'            => '状态值非法',
    ];

    protected $scene = [
        'add'  => ['smartpark_id', 'name', 'channels', 'status'],
        'edit' => ['name' => 'max:100', 'status'],
    ];
}
