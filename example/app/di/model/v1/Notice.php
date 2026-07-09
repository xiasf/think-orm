<?php
// +----------------------------------------------------------------------
// | di 模块 Notice 模型（完整演示 yf 风格）
// | 演示：
// |   1) 自动写入时间戳（int 类型，字段名 add_time）
// |   2) JSON 字段类型转换（payload）
// |   3) 字段格式化（channels 字符串 <-> 数组）
// |   4) 追加字段（append: status_text）
// |   5) 关联（smartpark_info belongsTo）
// |   6) readonly（smartpark_id）
// +----------------------------------------------------------------------

namespace app\di\model\v1;

use app\di\model\BModel;

class Notice extends BModel
{
    protected $table = 'di_notice';

    // 自动写入时间戳（int）
    protected $autoWriteTimestamp = true;
    protected $createTime = 'add_time';
    protected $updateTime = null;
    // 关闭读取时的 datetime 格式化（保持 int 原值，与 yf 一致）
    protected $dateFormat = false;

    // 字段类型转换
    protected $type = [
        'payload' => 'json',
    ];

    // 追加虚拟字段
    protected $append = ['status_text'];

    // 场景（必须 public，否则 Model::__set 会写入 data 而非属性）
    public $scene = 'add';

    /**
     * 自定义 initialize（子类必须调 parent）
     */
    protected function initialize($model = '', $class = '')
    {
        parent::initialize('', __CLASS__);
    }

    /**
     * status_text 虚拟字段
     */
    public function getStatusTextAttr($value, $data)
    {
        $map = [0 => '待发送', 1 => '已发送', 2 => '失败'];
        $status = $data['status'] ?? null;
        return $map[$status] ?? '未知';
    }

    /**
     * channels 写入：数组 → 逗号字符串
     */
    protected function setChannelsAttr($value, $data)
    {
        if (is_array($value)) {
            return join(',', $value);
        }
        return $value;
    }

    /**
     * channels 读取：逗号字符串 → 数组
     */
    protected function getChannelsAttr($value, $data)
    {
        if (empty($value)) return [];
        return explode(',', $value);
    }

    /**
     * 关联：所属园区
     */
    public function smartparkInfo()
    {
        if (!array_key_exists('smartpark_id', $this->data)) {
            $this->data['smartpark_id'] = null;
        }
        return $this->belongsTo(\app\di\model\v1\Smartpark::class, 'smartpark_id', 'id')
            ->field('id,name,number');
    }
}
