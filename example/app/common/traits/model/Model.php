<?php
// +----------------------------------------------------------------------
// | yf 项目 app\common\traits\model\Model 移植版
// | 提供：场景化保存(spd/sca)、索引化查询(listIndexBy)、
// |      动态查询构建(fieldWhere/withModel/withScope)、
// |      Query options 闭包注入(rollbackQuery)
// +----------------------------------------------------------------------

namespace app\common\traits\model;

use think\db\Query;
use think\Exception;

trait Model
{
    /**
     * 场景化更新
     */
    public function spd($data)
    {
        if (!$data) return false;
        $m = new $this->class;
        $res = $m->validate($this->curr_model . "." . $this->scene)
            ->allowField(true)->isUpdate(true)->save($data);
        if ($res === false) {
            $this->error = $m->getError();
            return false;
        }
        return $res;
    }

    /**
     * 场景化新增
     */
    public function sca($data)
    {
        if (!$data) return false;
        $m = new $this->class;
        $res = $m->validate($this->curr_model . "." . $this->scene)
            ->allowField(true)->isUpdate(false)->save($data);
        if ($res === false) {
            $this->error = $m->getError();
            return false;
        }
        return $m->toArray();
    }

    /**
     * 索引化查询：返回 [$index => $row]
     */
    public function listIndexBy($where = null, $index = 'id', $field = '', $limit = 1000, $order = 'id desc')
    {
        $m = new $this->class();
        $q = $m->where($where)->limit($limit)->order($order);
        $res = $field ? $q->column($field, $index) : $q->column('*', $index);
        return $res ? collection($res)->toArray() : null;
    }

    public function listIndexByIds($ids, $index = 'id', $field = '', $limit = 1000, $order = 'id desc')
    {
        return $this->listIndexBy([$index => ['in', $ids]], $index, $field, $limit, $order);
    }

    // —— 钩子：子类可重写以下方法配置默认行为 ——

    public function get_FieldRule()    { return []; }   // ['字段' => '表达式'|'like']
    public function get_withModel()    { return []; }   // ['关联1','关联2']
    public function get_Scope()        { return []; }   // ['scope1','scope2']
    public function get_ExtendField()  { return []; }   // 额外允许的查询字段

    /**
     * 按表字段自动构建 where
     */
    public function fieldWhere($params = null)
    {
        if (!$params) return $this;
        $fields = $this->getQuery()->getTableFields();
        $extend = $this->get_ExtendField();
        if (!empty($extend)) $fields = array_merge($fields, $extend);
        $fieldRule = $this->get_FieldRule();

        foreach ($fields as $field) {
            if (isset($params[$field])) {
                $expression = $fieldRule[$field] ?? '=';
                $value = $params[$field];
                if ($expression === 'like') $value = "%$value%";
                $this->getQuery()->where($field, $expression, $value);
            }
        }
        return $this;
    }

    /**
     * 链式 with 关联预加载
     */
    public function withModel($model = '')
    {
        $model = $model ?: $this->get_withModel();
        if (empty($model)) return $this;
        foreach ((array) $model as $m) {
            $this->getQuery()->with($m);
        }
        return $this;
    }

    /**
     * 链式调用 scope
     */
    public function withScope($scope = '')
    {
        $scope = $scope ?: $this->get_Scope();
        if (empty($scope)) return $this;
        foreach ((array) $scope as $s) {
            $this->$s();
        }
        return $this;
    }

    /**
     * 通过 Closure::bind 把 options 重新注入 Query（恢复 where 等条件）
     */
    public function rollbackQuery($options)
    {
        $hook = function ($options) {
            $this->options($options);
            return $this;
        };
        $func = \Closure::bind($hook, $this->getQuery(), Query::class);
        return $func($options);
    }
}
