<?php
// +----------------------------------------------------------------------
// | parkinglot 模块 CarParking 模型（pt_car_parking）
// | 演示作为 hasMany 的目标（Car->fixcarList）
// +----------------------------------------------------------------------

namespace app\parkinglot\model\v1;

use app\parkinglot\model\BModel;

class CarParking extends BModel
{
    protected $table = 'pt_car_parking';

    protected $autoWriteTimestamp = true;
    protected $createTime = 'add_time';
    protected $updateTime = null;
    protected $dateFormat = false;

    protected function initialize($model = '', $class = '')
    {
        parent::initialize('', __CLASS__);
    }
}
