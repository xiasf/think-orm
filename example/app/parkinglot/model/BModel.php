<?php
// +----------------------------------------------------------------------
// | parkinglot 模块基类（移植自 yf application/parkinglot/model/BModel.php）
// | - 双 readonly 字段：smartpark_id / parkinglot_id
// | - 3 个默认关联：smartparkInfo / parkinglotInfo / passagewayInfo
// | - 2 个 helper：useWithSp() / useWithPt()
// | - 3 个兜底访问器：缺字段时返回 null 而非抛 InvalidArgumentException
// +----------------------------------------------------------------------

namespace app\parkinglot\model;

use app\common\BaseModel;

class BModel extends BaseModel
{
    /** @var string|null 当前模块标识，trait 的 spd/sca 用 */
    public $model = null;

    // 这两个只读字段不能改，尤其是切换园区场景容易导致数据错乱（移植自 yf）
    protected $readonly = ['smartpark_id', 'parkinglot_id'];

    protected function initialize($model = '', $class = '')
    {
        $arr = explode("\\", $class);
        // yf 风格：$arr[1]=模块名，$arr[4]=类名 → 例如 'parkinglot/Car'
        if (isset($arr[1]) && isset($arr[4])) {
            $this->model = $arr[1] . "/" . $arr[4];
        }
        parent::initialize($arr[4] ?? '', $class);
    }

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
        return $this->belongsTo(\app\parkinglot\model\v1\Smartpark::class, 'smartpark_id', 'id')
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
        return $this->belongsTo(\app\parkinglot\model\v1\Parkinglot::class, 'parkinglot_id', 'id');
    }
}
