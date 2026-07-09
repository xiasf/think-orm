<?php
// +----------------------------------------------------------------------
// | parkinglot 模块 Car 模型（pt_car）
// |
// | 关联演示：
// |   1) $insert 自动字段：新增时自动写入 is_temp_number / is_new_energy
// |   2) belongsTo Smartpark + ->where()  条件关联（status=1 AND is_del=0）
// |   3) hasMany FixCar                一对多（这里复用 CarParking 作为占位）
// |   4) belongsToMany CarOwner        多对多车主
// |   5) 多层嵌套 with（useWithFull）：fixcar_list.smartpark_info / .parkinglot_info
// +----------------------------------------------------------------------

namespace app\parkinglot\model\v1;

use app\parkinglot\model\BModel;
use think\Request;

class Car extends BModel
{
    protected $table = 'pt_car';

    protected $autoWriteTimestamp = true;
    protected $createTime = 'add_time';
    protected $updateTime = null;
    protected $dateFormat = false;

    /**
     * 新增时自动写入这两个字段（配合下面的 setIsXxxAttr 修改器）
     */
    protected $insert = ['is_temp_number', 'is_new_energy'];
/**
     * 多层嵌套 with：fixcar_list 下面还嵌套了 smartpark_info / parkinglot_info / 等
     * 这是 yf 真实业务里查一辆车全量信息的标准用法
     */
    public function useWithFull()
    {
        return $this->useWith([
            'smartpark_info',
            'fixcar_list' => [
                'smartpark_info',
                'parkinglot_info',
            ],
            'car_owner_list',
        ]);
    }

    /**
     * 修改器：is_temp_number
     * 显式传值则尊重；否则按车牌是否含"临"判断
     */
    protected function setIsTempNumberAttr($value, $data)
    {
        if (is_null($value) || $value === '') {
            $number = $data['number'] ?? '';
            return stripos($number, '临') === false ? 0 : 1;
        }
        return $value;
    }

    /**
     * 修改器：is_new_energy
     * 显式传值则尊重；否则按车牌颜色（这里简化为 8 位车牌号 → 新能源）
     */
    protected function setIsNewEnergyAttr($value, $data)
    {
        if (is_null($value) || $value === '') {
            $number = $data['number'] ?? '';
            // 新能源车牌长度为 8，普通车牌为 7（简化判定，按字符数）
            return mb_strlen($number, 'UTF-8') >= 8 ? 1 : 0;
        }
        return $value;
    }

    /**
     * 缺字段兜底：last_smartpark_id
     */
    public function getLastSmartparkIdAttr($value, $data)
    {
        if (!array_key_exists('last_smartpark_id', $data)) {
            return null;
        }
        return $value;
    }

    /**
     * 关联：最后出入的园区（条件关联：status=1 AND is_del=0）
     */
    public function smartparkInfo()
    {
        if (!array_key_exists('last_smartpark_id', $this->data)) {
            $this->data['last_smartpark_id'] = null;
        }
        return $this->belongsTo(Smartpark::class, 'last_smartpark_id', 'id')
            ->field('id,name,number,desp,detail_address')
            ->where(['status' => 1, 'is_del' => 0]);
    }

    /**
     * 缺字段兜底：id
     */
    public function getIdAttr($value, $data)
    {
        if (!array_key_exists('id', $data)) {
            return null;
        }
        return $value;
    }

    /**
     * 关联：停车记录一对多（演示 hasMany）
     * 复用 pt_car_parking 表（如有）；这里简化指向 CarParking 模型
     */
    public function fixcarList()
    {
        if (!array_key_exists('id', $this->data)) {
            $this->data['id'] = null;
        }
        // yf 中是 hasMany(FixCar::class, 'car_id', 'id')
        // 这里指向 CarParking 演示一对多关系
        return $this->hasMany(CarParking::class, 'car_id', 'id');
    }

    /**
     * 关联：车主列表（多对多，pivot 表 pt_car_car_owner）
     *
     * 支持运行时按 smartpark_id 过滤 pivot
     */
    public function carOwnerList()
    {
        if (!array_key_exists('id', $this->data)) {
            $this->data['id'] = null;
        }

        $belongsToMany = $this->belongsToMany(CarOwner::class, 'pt_car_car_owner', 'car_owner_id', 'car_id');

        $smartparkId = Request::instance()->param('smartpark_id/d', 0);
        if ($smartparkId) {
            $belongsToMany->getQuery()->where(['pivot.smartpark_id' => $smartparkId]);
        }

        return $belongsToMany;
    }
}
