<?php

namespace ThinkOrm\Tests\Fixture;

use think\Model;

class AccessorUser extends Model
{
    protected $table = 'users';
    protected $autoWriteTimestamp = false;

    public function getNameAttr($value)
    {
        return strtoupper($value);
    }

    public function setNameAttr($value)
    {
        return strtolower($value);
    }

    public function getFullNameAttr($value, $data)
    {
        return strtoupper($data['name']) . ' <' . $data['email'] . '>';
    }
}
