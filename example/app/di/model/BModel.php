<?php
// +----------------------------------------------------------------------
// | di 模块基类（示例）
// | - 自定义 initialize 把 curr_model 拼成 di/Notice
// | - 演示 protected $readonly：smartpark_id 创建后不可改
// +----------------------------------------------------------------------

namespace app\di\model;

use app\common\BaseModel;

class BModel extends BaseModel
{
    protected $model = null;

    protected $readonly = ['smartpark_id'];

    protected function initialize($model = '', $class = '')
    {
        $arr = explode("\\", $class);
        // yf BModel：$arr[1] = 模块名，$arr[4] = 类名
        $this->model = isset($arr[1]) && isset($arr[4]) ? $arr[1] . "/" . $arr[4] : end($arr);
        parent::initialize($arr[4] ?? '', $class);
    }

    /**
     * 自定义读写器示例：smartpark_id 缺失时返回 null
     */
    public function getSmartparkIdAttr($value, $data)
    {
        if (!array_key_exists('smartpark_id', $data)) return null;
        return $value;
    }
}
