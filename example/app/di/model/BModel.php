<?php
// +----------------------------------------------------------------------
// | di 模块基类
// | - protected $readonly：smartpark_id 创建后不可改
// | - 演示缺字段兜底读写器
// | 不再需要 initialize() override —— 验证器路径由 BaseModel::validatorName()
// | 从命名空间自动推断（app\di\model\..\Xxx → "di/Xxx"）
// +----------------------------------------------------------------------

namespace app\di\model;

use app\common\BaseModel;

class BModel extends BaseModel
{
    protected $readonly = ['smartpark_id'];

    /**
     * 自定义读写器示例：smartpark_id 缺失时返回 null
     */
    public function getSmartparkIdAttr($value, $data)
    {
        if (!array_key_exists('smartpark_id', $data)) return null;
        return $value;
    }
}
