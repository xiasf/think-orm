<?php
// +----------------------------------------------------------------------
// | parkinglot 模块 User 模型（pt_user）
// | 被 CarOwner::userInfo 通过 ->bind() 绑定字段（name/face/email/mobile...）
// +----------------------------------------------------------------------

namespace app\parkinglot\model\v1;

use app\parkinglot\model\BModel;

class User extends BModel
{
    protected $table = 'pt_user';

    protected $autoWriteTimestamp = true;
    protected $createTime = 'add_time';
    protected $updateTime = null;
    protected $dateFormat = false;
}
