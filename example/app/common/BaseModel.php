<?php
// +----------------------------------------------------------------------
// | 兼容 yf 项目的 BaseModel（精简移植版）
// | - 保留：initialize / search / search_or / info / listBy / add / upd / del /
// |        countBy / maxBy / minBy / avgBy / sumBy / valueBy / inc / dec /
// |        upSert / resultSet（decimal → float）/ resultListSet
// | - validateData 包了 set_error_handler：规则写错（如 require|integeregt:0）时
// |   会抛 ValidateException 而不是静默失败，便于排查（移植自 yf 实战经验）
// | - 去除：upload（依赖 think\Image、request()->file()）
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

    /** @var string 当前模块/模型标识，用于 validate('module/scene') */
    public $curr_model = null;

    /** @var string 默认验证场景，子类可重写（必须 public，否则 __set 会写入 data） */
    public $scene = '';

    /** @var string|null 最近一次操作的错误信息（必须 public，否则 __get 抛异常） */
    public $error = null;

    /**
     * 子类 initialize 第一行应调用 parent::initialize('', __CLASS__)
     *
     * @param string $model 模块名（默认空）
     * @param string $class 完整类名（默认空）
     */
    protected function initialize($model = '', $class = '')
    {
        parent::initialize();
        if ($class) {
            $arr = explode("\\", $class);
            // yf 风格：[app/<module>/.../<Name>]，curr_model = "<module>/<Name>"
            // app\di\model\v1\Notice → $arr[1]='di', $arr[4]='Notice' → 'di/Notice'
            if (isset($arr[1]) && isset($arr[4])) {
                $this->curr_model = $arr[1] . "/" . $arr[4];
            } else {
                $this->curr_model = end($arr);
            }
            $this->class = $class;
        }
    }

    /**
     * 链式预加载关联（yf 风格）
     */
    public function useWith($name)
    {
        $this->getQuery()->with($name);
        return $this;
    }

    /**
     * 简易列表查询
     */
    public function lists($where = [], $field = '', $order = '', $limit = null)
    {
        $m = new $this->class();
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
        $page = (int) $page;
        if ($page == 0) $page = 1;
        $page_size = (int) $page_size;
        if ($page_size <= 0) $page_size = 100;

        $limit = ($page == -1) ? null : ($page - 1) * $page_size;

        $where = array_filter((array) $where, function ($v) { return $v !== ''; });

        $m = new $this->class();
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
     *
     * @param array $where    AND 条件
     * @param array $where_or OR 条件（任一满足）
     * @param string $order
     * @param int $page       -1 表示不分页
     * @param int $page_size
     * @param int $count      输出：总记录数
     * @return array
     */
    public function search_or($where = null, $where_or = null, $order = 'id desc', $page = 1, $page_size = 1000, &$count = 0)
    {
        $page = (int) $page;
        if ($page == 0) $page = 1;
        $page_size = (int) $page_size;
        if ($page_size <= 0) $page_size = 100;

        $limit = ($page == -1) ? null : ($page - 1) * $page_size;

        $where    = array_filter((array) $where,    function ($v) { return $v !== ''; });
        $where_or = array_filter((array) $where_or, function ($v) { return $v !== ''; });

        $m = new $this->class();
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

    /**
     * 按主键取单条
     */
    public function info($id, $field = '', $order = '', $is_lock = null)
    {
        return $this->infoBy(['id' => $id], $field, $order, $is_lock);
    }

    /**
     * 按条件取单条（返回数组）
     */
    public function infoBy($where, $field = '', $order = '', $is_lock = null)
    {
        $m = new $this->class;
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

    /**
     * 列表结果处理
     */
    public function resultListSet($result)
    {
        return $result ? collection($result)->toArray() : $result;
    }

    /**
     * 按主键集合取列表
     */
    public function listByIds($ids, $field = '', $limit = 1000, $order = 'id desc')
    {
        return $this->listBy(['id' => ['in', $ids]], $field, $limit, $order);
    }

    public function listBy($where = null, $field = '', $limit = 1000, $order = 'id desc')
    {
        $m = new $this->class();
        $q = $m->where($where)->limit($limit)->order($order);
        if ($field) $q->field($field);
        return $this->resultListSet($q->select());
    }

    public function listPageBy($where = null, $field = '', $page = 1, $page_size = 1000, $order = 'id desc')
    {
        $page = max(1, (int) $page);
        $offset = $page_size * ($page - 1);
        $m = new $this->class();
        $q = $m->where($where)->limit($offset, $page_size)->order($order);
        if ($field) $q->field($field);
        return $this->resultListSet($q->select());
    }

    /**
     * 新增（yf add）
     */
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

        $m = new $this->class;
        $res = $m->validate($this->curr_model . ".add")->allowField(true)->isUpdate(false)->save($data);
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
        $m = new $this->class;
        $res = $m->validate($this->curr_model . ".add")->allowField(true)->isUpdate(false)->saveAll($list, false);
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
        $m = new $this->class;
        $res = $m->validate($this->curr_model . ".edit")->allowField(true)->isUpdate(true)->save($data);
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
        $m = new $this->class;
        $res = $m->validate($this->curr_model . ".edit")->allowField(true)->isUpdate(true)->saveAll($datas, true);
        return $res[0]->data;
    }

    public function updAttr($ids, $field_name, $field_val)
    {
        $ids = is_array($ids) ? implode(',', $ids) : $ids;
        return $this->updBy([$field_name => $field_val], ['id' => ['in', $ids]]);
    }

    public function updBy($data, $where)
    {
        $m = new $this->class;
        $res = $m->allowField(true)->isUpdate(true)->save($data, $where);
        if ($res === false) {
            $this->error = $m->getError();
            return false;
        }
        return $res;
    }

    public function del($ids)
    {
        $m = new $this->class;
        return $m::destroy($ids);
    }

    public function delBy($where)
    {
        $m = new $this->class;
        return $m::destroy($where);
    }

    public function countBy($where = '', $field = 'id')
    {
        $m = new $this->class;
        return $where ? $m::where($where)->count($field) : $m::count($field);
    }

    public function maxBy($where, $field)
    {
        $m = new $this->class;
        return $where ? $m::where($where)->max($field) : $m::max($field);
    }

    public function minBy($where, $field)
    {
        $m = new $this->class;
        return $where ? $m::where($where)->min($field) : $m::min($field);
    }

    public function avgBy($where, $field)
    {
        $m = new $this->class;
        return $where ? $m::where($where)->avg($field) : $m::avg($field);
    }

    public function sumBy($where, $field)
    {
        $m = new $this->class;
        return $where ? $m::where($where)->sum($field) : $m::sum($field);
    }

    public function inc($where, $field_name, $field_val = 1)
    {
        return (new $this->class)->where($where)->setInc($field_name, $field_val);
    }

    public function dec($where, $field_name, $field_val)
    {
        return (new $this->class)->where($where)->setDec($field_name, $field_val);
    }

    public function valueBy($where = null, $field, $order = 'id desc')
    {
        $m = new $this->class();
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
        $sql = (new $this->class)->fetchSql(true)->insertAll($arr);
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
