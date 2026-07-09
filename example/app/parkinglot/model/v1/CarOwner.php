<?php
// +----------------------------------------------------------------------
// | parkinglot 模块 CarOwner 模型（pt_car_owner）
// |
// | 关联演示：
// |   1) belongsTo User + ->bind()  把 User 的字段绑定到自己身上（tp 5.0.24 的字段绑定）
// |   2) belongsToMany Car  多对多车辆，pivot 表 pt_car_car_owner
// |      支持运行时按 smartpark_id 过滤 pivot（移植 yf 真实业务约束）
// +----------------------------------------------------------------------

namespace app\parkinglot\model\v1;

use app\parkinglot\model\BModel;
use think\Request;

class CarOwner extends BModel
{
    protected $table = 'pt_car_owner';

    protected $autoWriteTimestamp = true;
    protected $createTime = 'add_time';
    protected $updateTime = null;
    protected $dateFormat = false;

    /**
     * 一键预加载全部关联
     */
    public function useWithFull()
    {
        return $this->useWith([
            'user_info',
            'car_list',
        ]);
    }

    /**
     * 缺字段兜底：user_id
     */
    public function getUserIdAttr($value, $data)
    {
        if (!array_key_exists('user_id', $data)) {
            return 0;
        }
        return $value;
    }

    /**
     * 关联：所属用户
     * 关键点：->bind() 把 User 的字段绑定到 CarOwner 实例上（读出来像本地字段）
     */
    public function userInfo()
    {
        if (!array_key_exists('user_id', $this->data)) {
            $this->data['user_id'] = 0;
        }
        return $this->belongsTo(User::class, 'user_id', 'id')->bind([
            'name'      => 'name',
            'face'      => 'face',
            'email'     => 'email',
            'mobile'    => 'mobile',
            'nick_name' => 'nick_name',
            'real_name' => 'real_name',
        ]);
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
     * 关联：车辆列表（多对多）
     *
     * 支持运行时按 smartpark_id 过滤 pivot：
     *   request()->param('smartpark_id/d', 0) 取参数；
     *   有值则给 belongsToMany 加 pivot.smartpark_id = ? 条件。
     *
     * 这就是 yf 真实场景：一个车主在多园区关联车辆，需要按当前园区筛。
     */
    public function carList()
    {
        if (!array_key_exists('id', $this->data)) {
            $this->data['id'] = null;
        }

        $belongsToMany = $this->belongsToMany(Car::class, 'pt_car_car_owner', 'car_id', 'car_owner_id');

        $smartparkId = Request::instance()->param('smartpark_id/d', 0);
        if ($smartparkId) {
            $belongsToMany->getQuery()->where(['pivot.smartpark_id' => $smartparkId]);
        }

        return $belongsToMany;
    }
}
