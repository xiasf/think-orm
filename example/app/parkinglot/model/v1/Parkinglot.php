<?php
// +----------------------------------------------------------------------
// | parkinglot 模块 Parkinglot 模型（pt_parkinglot）
// +----------------------------------------------------------------------

namespace app\parkinglot\model\v1;

use app\parkinglot\model\BModel;

class Parkinglot extends BModel
{
    protected $table = 'pt_parkinglot';

    protected $autoWriteTimestamp = true;
    protected $createTime = 'add_time';
    protected $updateTime = null;
    protected $dateFormat = false;

    protected function initialize($model = '', $class = '')
    {
        parent::initialize('', __CLASS__);
    }
}
