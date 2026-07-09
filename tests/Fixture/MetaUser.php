<?php

namespace ThinkOrm\Tests\Fixture;

use think\Model;

class MetaUser extends Model
{
    protected $table = 'users';
    protected $autoWriteTimestamp = false;

    // meta 字段为 LONGTEXT，手动 JSON 编解码作为 JSON 类型使用
    protected $type = [
        'meta' => 'json',
    ];
}
