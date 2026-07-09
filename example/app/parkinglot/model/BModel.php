<?php
// +----------------------------------------------------------------------
// | parkinglot 模块基类（移植自 yf application/parkinglot/model/BModel.php）
// | - 双 readonly 字段：smartpark_id / parkinglot_id
// | - 3 个默认关联：smartparkInfo / parkinglotInfo
// | - 2 个 helper：useWithSp() / useWithPt()
// | - 2 个兜底访问器：缺字段时返回 null 而非抛 InvalidArgumentException
// |
// | 不再需要 initialize() override —— 验证器路径由 BaseModel::validatorName()
// | 从命名空间自动推断（app\parkinglot\model\..\Xxx → "parkinglot/Xxx"）
// +----------------------------------------------------------------------

namespace app\parkinglot\model;

use app\common\BaseModel;
use app\parkinglot\model\v1\Smartpark;
use app\parkinglot\model\v1\Parkinglot;

class BModel extends BaseModel
{
    // 这两个只读字段不能改，尤其是切换园区场景容易导致数据错乱（移植自 yf）
    protected $readonly = ['smartpark_id', 'parkinglot_id'];

    /**
     * 便捷 helper：预加载 smartpark_info 关联
     */
    public function useWithSp()
    {
        return $this->useWith(['smartpark_info']);
    }

    /**
     * 便捷 helper：预加载 parkinglot_info 关联
     */
    public function useWithPt()
    {
        return $this->useWith(['parkinglot_info']);
    }

    /**
     * 缺字段兜底：smartpark_id 不存在时返回 null（避免 with 时抛 property not exists）
     */
    public function getSmartparkIdAttr($value, $data)
    {
        if (!array_key_exists('smartpark_id', $data)) {
            return null;
        }
        return $value;
    }

    /**
     * 关联：所属园区（条件：status=1 AND is_del=0，移植 yf 实战约束）
     */
    public function smartparkInfo()
    {
        if (!array_key_exists('smartpark_id', $this->data)) {
            $this->data['smartpark_id'] = null;
        }
        return $this->belongsTo(Smartpark::class, 'smartpark_id', 'id')
            ->field('id,name,number,desp,detail_address')
            ->where(['status' => 1, 'is_del' => 0]);
    }

    /**
     * 缺字段兜底：parkinglot_id
     */
    public function getParkinglotIdAttr($value, $data)
    {
        if (!array_key_exists('parkinglot_id', $data)) {
            return null;
        }
        return $value;
    }

    /**
     * 关联：所属停车场
     */
    public function parkinglotInfo()
    {
        if (!array_key_exists('parkinglot_id', $this->data)) {
            $this->data['parkinglot_id'] = null;
        }
        return $this->belongsTo(Parkinglot::class, 'parkinglot_id', 'id');
    }
}
