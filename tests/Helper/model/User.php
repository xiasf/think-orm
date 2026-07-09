<?php
// 显式放到 app\model 命名空间下，配合 model() helper 默认解析路径
// 测试通过 App::$namespace 临时切换到 ThinkOrm\Tests\Helper 命名空间

namespace ThinkOrm\Tests\Helper\model;

use think\Model;
use think\traits\model\SoftDelete;

class User extends Model
{
    use SoftDelete;

    protected $table = 'users';
    protected $autoWriteTimestamp = false;
    protected $deleteTime = 'delete_time';

    protected $hidden = ['email'];
    protected $append = ['upper_name'];

    // 只读字段：update 时不能改
    protected $readonly = ['name'];

    public function getUpperNameAttr($value, $data)
    {
        return strtoupper($data['name']);
    }

    public function posts()
    {
        return $this->hasMany(\ThinkOrm\Tests\Helper\model\Post::class);
    }

    // 命名范围
    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

    // 事件钩子：before_write 时记录 name 字段
    public static $eventLog = [];

    public static function onBeforeWrite($model)
    {
        // 注意：$model->name 访问的是 Model 的 protected $name 属性（模型名），
        // 不是 data 中的 name 字段；必须用 getData() 或 $model['name']
        self::$eventLog[] = 'before_write:' . ($model->getData('name') ?? '');
    }

    public static function onAfterInsert($model)
    {
        self::$eventLog[] = 'after_insert:' . ($model->getData('name') ?? '');
    }
}
