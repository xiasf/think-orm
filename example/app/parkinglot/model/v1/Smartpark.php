<?php
// +----------------------------------------------------------------------
// | parkinglot 模块 Smartpark 模型（pt_smartpark）
// | 园区主表，被各业务表 belongsTo 引用
// +----------------------------------------------------------------------

namespace app\parkinglot\model\v1;

use app\parkinglot\model\BModel;

class Smartpark extends BModel
{
    protected $table = 'pt_smartpark';
    protected $autoWriteTimestamp = false;
}
