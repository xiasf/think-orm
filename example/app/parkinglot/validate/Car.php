<?php
// +----------------------------------------------------------------------
// | parkinglot 模块 Car 验证器（演示 yf 自定义规则）
// +----------------------------------------------------------------------

namespace app\parkinglot\validate;

class Car extends BaseValidator
{
    protected $rule = [
        'number'         => 'require|max:20',
        'smartpark_id'   => 'require|integer|>:0',
        'parkinglot_id'  => 'require|integer|>:0',
        // 演示 sometimes：mobile 字段存在时才校验格式
        'mobile'         => 'sometimes|regex:^1\d{10}$',
        // 演示 conflict：email 存在时 mobile 和 name 都不能存在
        'email'          => 'conflict:mobile,name',
        // 演示 r_if：contact_type 等于 phone 或 sms 时 mobile 必填
        'contact_value'  => 'r_if:contact_type,phone,sms',
        // 演示 r_with：name 或 email 出现时 nick_name 必填
        'nick_name'      => 'r_with:name,email',
    ];

    protected $message = [
        'number.require'        => '车牌号必填',
        'number.max'            => '车牌号长度不能超过 20',
        'smartpark_id.require'  => '园区ID必填',
        'smartpark_id.>'        => '园区ID必须大于 0',
        'parkinglot_id.require' => '停车场ID必填',
        'parkinglot_id.>'       => '停车场ID必须大于 0',
        'mobile.regex'          => '手机号格式错误',
        'email.conflict'        => 'email 不能与 mobile/name 同时存在',
        'contact_value.r_if'    => '联系方式为 phone/sms 时，contact_value 必填',
        'nick_name.r_with'      => 'name 或 email 存在时，nick_name 必填',
    ];

    protected $scene = [
        'add'  => ['number', 'smartpark_id', 'parkinglot_id', 'mobile', 'email', 'contact_value', 'nick_name'],
        'edit' => ['number' => 'max:20', 'mobile', 'email'],
    ];
}
