<?php

namespace app\di\model\v1;

use app\di\model\BModel;

class Smartpark extends BModel
{
    protected $table = 'di_smartpark';
    protected $autoWriteTimestamp = false;

    protected function initialize($model = '', $class = '')
    {
        parent::initialize('', __CLASS__);
    }
}
