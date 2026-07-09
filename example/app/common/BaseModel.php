<?php
// +----------------------------------------------------------------------
// | app\common\BaseModel —— yf 风格 BaseModel 的干净移植
// |
// | 设计要点（与原 yf 版本的差异）：
// |   1. 不再需要 initialize($model, $class) 手工解析命名空间
// |      - PHP 原生 static::class 直接给出 FQCN
// |      - 验证器路径（"<module>/<Name>"）由 validatorName() 从命名空间推断
// |   2. 所有"new 一个新的自己"用 new static()（PHP 晚静态绑定）
// |      - 子类（Car / Notice 等）自动得到正确实例，无需重写 initialize
// |   3. 关联关系里直接 use 目标类、用 ModelClass::class
// |      - 比写 model('xxx/xxx')->class 更直接、IDE 可跳转、PHPStan 可静态分析
// |
// | 保留：search / search_or / info / infoBy / lists / listBy / listByIds /
// |      listPageBy / add / adds / upd / upds / updBy / updAttr / del / delBy /
// |      countBy / maxBy / minBy / avgBy / sumBy / valueBy / inc / dec /
// |      upSert / resultSet（decimal → float）/ resultListSet
// |
// | validateData 包了 set_error_handler：规则写错（如 require|integeregt:0）时
// | 会抛 ValidateException 而不是静默通过（移植自 yf 实战经验）。
// +----------------------------------------------------------------------

namespace app\common;

use think\Db;
use think\Loader;
use think\Model;
use think\exception\ValidateException;
use app\common\traits\model\Model as TModel;

class BaseModel extends Model
{
    use TModel;

    /**
     * 当前验证场景（add / edit / 子类自定义）
     * 必须 public：TP 5.0 的 Model::__set 会拦截对 protected/private 属性的写入
     * @var string
     */
    public $scene = '';

    /**
     * 最近一次操作的错误信息
     * 必须 public：同上
     * @var string|null
     */
    public $error = null;

    /**
     * 验证器路径覆盖
     * 默认为 null，表示由 validatorName() 从命名空间推断。
     * 子类如需指向与命名空间约定不同的验证器，重写本属性即可。
     *
     * 例：
     *   protected $validatorName = 'parkinglot/Car';   // 显式指定
     *   protected $validatorName = null;               // 默认：从命名空间推断
     *
     * @var string|null
     */
    protected $validatorName = null;

    /**
     * 取验证器路径（用于 validate('module/Name.scene') 解析）
     *
     * 推断规则（默认）：
     *   命名空间 app\<module>\model\...\Xxx  →  "<module>/Xxx"
     *   例：app\parkinglot\model\v1\Car  →  "parkinglot/Car"
     *       app\di\model\v1\Notice       →  "di/Notice"
     *
     * @return string|null
     */
    public function validatorName()
    {
        if ($this->validatorName !== null) {
            return $this->validatorName;
        }
        // static::class —— PHP 原生晚静态绑定，给出真实子类 FQCN
        $parts = explode('\\', static::class);
        if (isset($parts[0], $parts[1]) && $parts[0] === 'app') {
            return $parts[1] . '/' . end($parts);
        }
        return end($parts) ?: null;
    }

    // —— 链式预加载 ——

    public function useWith($name)
    {
        $this->getQuery()->with($name);
        return $this;
    }

    // —— 列表查询 ——

    public function lists($where = [], $field = '', $order = '', $limit = null)
    {
        $m = new static();
        if ($where) $m->where($where);
        if ($field) $m->field($field);
        if ($order) $m->order($order);
        if ($limit) $m->limit($limit);
        return $this->resultListSet($m->select());
    }

    /**
     * 分页 + 排序搜索
     */
    public function search($where = null, $order = 'id desc', $page = 1, $page_size = 100, &$count = 0)
    {
        $page = max(1, (int) $page);
        $page_size = (int) $page_size;
        if ($page_size <= 0) $page_size = 100;

        $limit = ($page == -1) ? null : ($page - 1) * $page_size;

        $where = array_filter((array) $where, function ($v) { return $v !== ''; });

        $m = new static();
        $m->where($where);
        if ($limit !== null) $m->limit($limit, $page_size);
        $m->order($order);

        $options = $m->getQuery()->getOptions();
        $bind = $m->getQuery()->getBind();
        $res = $m->bind($bind)->select();

        unset($options['order'], $options['limit'], $options['page'], $options['field']);
        $count = $m->rollbackQuery($options)->bind($bind)->count();

        return $this->resultListSet($res);
    }

    /**
     * AND + OR 混合搜索：where AND (where_or 之间 OR)
     */
    public function search_or($where = null, $where_or = null, $order = 'id desc', $page = 1, $page_size = 1000, &$count = 0)
    {
        $page = max(1, (int) $page);
        $page_size = (int) $page_size;
        if ($page_size <= 0) $page_size = 100;

        $limit = ($page == -1) ? null : ($page - 1) * $page_size;

        $where    = array_filter((array) $where,    function ($v) { return $v !== ''; });
        $where_or = array_filter((array) $where_or, function ($v) { return $v !== ''; });

        $m = new static();
        $m->whereOr(function ($query) use ($where_or) {
            $query->whereOr($where_or);
        })->where($where);

        if ($limit !== null) $m->limit($limit, $page_size);
        $m->order($order);

        $options = $m->getQuery()->getOptions();
        $bind = $m->getQuery()->getBind();
        $res = $m->bind($bind)->select();

        unset($options['order'], $options['limit'], $options['page'], $options['field']);
        $count = $m->rollbackQuery($options)->bind($bind)->count();

        return $this->resultListSet($res);
    }

    // —— 单条查询 ——

    public function info($id, $field = '', $order = '', $is_lock = null)
    {
        return $this->infoBy(['id' => $id], $field, $order, $is_lock);
    }

    public function infoBy($where, $field = '', $order = '', $is_lock = null)
    {
        $m = new static;
        $query = $m->getQuery();
        if ($field) $query->field($field);
        if (!is_null($is_lock)) $query->lock($is_lock);
        $res = $query->where($where)->order($order)->find();
        return $res ? $res->toArray() : $res;
    }

    public function toArray()
    {
        $item = parent::toArray();
        return $this->resultSet($item);
    }

    /**
     * 单条结果处理：decimal 字段转 float
     */
    public function resultSet($item)
    {
        static $types = null;
        if ($types === null) {
            $types = $this->getQuery()->getFieldsType();
        }
        foreach ($item as $key => $value) {
            if (isset($types[$key]) && stripos($types[$key], 'decimal') !== false) {
                $item[$key] = floatval($value);
            }
        }
        return $item;
    }

    public function resultListSet($result)
    {
        return $result ? collection($result)->toArray() : $result;
    }

    // —— 批量查询 ——

    public function listByIds($ids, $field = '', $limit = 1000, $order = 'id desc')
    {
        return $this->listBy(['id' => ['in', $ids]], $field, $limit, $order);
    }

    public function listBy($where = null, $field = '', $limit = 1000, $order = 'id desc')
    {
        $m = new static();
        $q = $m->where($where)->limit($limit)->order($order);
        if ($field) $q->field($field);
        return $this->resultListSet($q->select());
    }

    public function listPageBy($where = null, $field = '', $page = 1, $page_size = 1000, $order = 'id desc')
    {
        $page = max(1, (int) $page);
        $offset = $page_size * ($page - 1);
        $m = new static();
        $q = $m->where($where)->limit($offset, $page_size)->order($order);
        if ($field) $q->field($field);
        return $this->resultListSet($q->select());
    }

    // —— 增删改 ——

    public function add($data, $method = '')
    {
        if (!$data) {
            $this->error = '没有新增数据';
            return false;
        }
        if ($method && is_string($method)) $data = $this->$method($data);

        if (isset($data['id']) && (!is_numeric($data['id']) || $data['id'] == 0)) {
            unset($data['id']);
        }

        $m = new static;
        $res = $m->validate($this->validatorName() . ".add")
            ->allowField(true)->isUpdate(false)->save($data);
        if ($res === false) {
            $this->error = $m->getError();
            return false;
        }
        $res = $m->toArray();
        if (!empty($res['id'])) $res['id'] = intval($res['id']);
        return $res;
    }

    public function adds($list)
    {
        if (!$list) {
            $this->error = '没有新增数据';
            return false;
        }
        $m = new static;
        $res = $m->validate($this->validatorName() . ".add")
            ->allowField(true)->isUpdate(false)->saveAll($list, false);
        return collection($res)->toArray();
    }

    public function upd($data, $method = '')
    {
        if (!$data) {
            $this->error = '没有更新数据';
            return false;
        }
        if ($method) {
            $data = is_string($method) ? $this->$method($data) : $method($data);
        }
        $m = new static;
        $res = $m->validate($this->validatorName() . ".edit")
            ->allowField(true)->isUpdate(true)->save($data);
        if ($res === false) {
            $this->error = $m->getError();
            return false;
        }
        return $res;
    }

    public function upds($datas)
    {
        if (!$datas) {
            $this->error = '没有更新数据';
            return false;
        }
        $m = new static;
        $res = $m->validate($this->validatorName() . ".edit")
            ->allowField(true)->isUpdate(true)->saveAll($datas, true);
        return $res[0]->data;
    }

    public function updAttr($ids, $field_name, $field_val)
    {
        $ids = is_array($ids) ? implode(',', $ids) : $ids;
        return $this->updBy([$field_name => $field_val], ['id' => ['in', $ids]]);
    }

    public function updBy($data, $where)
    {
        $m = new static;
        $res = $m->allowField(true)->isUpdate(true)->save($data, $where);
        if ($res === false) {
            $this->error = $m->getError();
            return false;
        }
        return $res;
    }

    public function del($ids)
    {
        return static::destroy($ids);
    }

    public function delBy($where)
    {
        return static::destroy($where);
    }

    // —— 聚合 ——

    public function countBy($where = '', $field = 'id')
    {
        return $where ? static::where($where)->count($field) : static::count($field);
    }

    public function maxBy($where, $field)
    {
        return $where ? static::where($where)->max($field) : static::max($field);
    }

    public function minBy($where, $field)
    {
        return $where ? static::where($where)->min($field) : static::min($field);
    }

    public function avgBy($where, $field)
    {
        return $where ? static::where($where)->avg($field) : static::avg($field);
    }

    public function sumBy($where, $field)
    {
        return $where ? static::where($where)->sum($field) : static::sum($field);
    }

    public function inc($where, $field_name, $field_val = 1)
    {
        return (new static)->where($where)->setInc($field_name, $field_val);
    }

    public function dec($where, $field_name, $field_val)
    {
        return (new static)->where($where)->setDec($field_name, $field_val);
    }

    public function valueBy($where = null, $field, $order = 'id desc')
    {
        $m = new static();
        return $m->where($where)->limit(1)->order($order)->value($field);
    }

    /**
     * MySQL UPSERT：批量插入 + ON DUPLICATE KEY UPDATE
     */
    public function upSert($arr)
    {
        $fields = array_keys($arr[0]);
        $frow = [];
        foreach ($fields as $field) {
            $frow[] = "$field=VALUES($field)";
        }
        $sql = (new static)->fetchSql(true)->insertAll($arr);
        $sql .= " ON DUPLICATE KEY UPDATE " . implode(',', $frow);
        return Db::execute($sql);
    }

    /**
     * 覆盖 Model::validateData
     *
     * 用 set_error_handler 包裹 validate->check()：
     * 验证规则写错时（如 'require|integeregt:0' 拼错），TP 原始逻辑会触发
     * PHP Warning 然后静默通过——非常难排查。这里把它转成 ValidateException。
     *
     * 移植自 yf BaseModel 2022-03-12 的实战经验。
     */
    protected function validateData($data, $rule = null, $batch = null)
    {
        $info = is_null($rule) ? $this->validate : $rule;

        if (empty($info)) {
            return true;
        }

        if (is_array($info)) {
            $validate = Loader::validate();
            $validate->rule($info['rule']);
            $validate->message($info['msg']);
        } else {
            $name = is_string($info) ? $info : $this->name;
            $scene = '';
            if (strpos($name, '.')) {
                list($name, $scene) = explode('.', $name);
            }
            $validate = Loader::validate($name);
            if (!empty($scene)) {
                $validate->scene($scene);
            }
        }
        $batch = is_null($batch) ? $this->batchValidate : $batch;

        set_error_handler(function ($code, $msg, $file, $line) {
            if (function_exists('iconv') && stripos($msg, 'iconv') === false) {
                $conv = @iconv('GBK', 'utf-8', $msg);
                if ($conv !== false) $msg = $conv;
            }
            $message = "Validate ERROR: [{$code}] {$msg} in file: {$file} on line: {$line}";
            if ((error_reporting() & $code) || in_array($code, [E_WARNING])) {
                throw new ValidateException($message);
            }
            return true;
        });

        try {
            $ok = $validate->batch($batch)->check($data);
        } finally {
            restore_error_handler();
        }

        if (!$ok) {
            $this->error = $validate->getError();
            if ($this->failException) {
                throw new ValidateException($this->error);
            }
            return false;
        }
        $this->validate = null;
        return true;
    }
}
