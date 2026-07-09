<?php
// +----------------------------------------------------------------------
// | parkinglot 验证器基类（移植自 yf application/parkinglot/validate/BaseValidator.php）
// |
// | 7 个 yf 业务规则（与 Laravel 命名对齐，因 TP 关键字冲突用 r_ 前缀替代 require_）：
// |   1) sometimes($value, $rule, $data, $field)         字段存在时才验证
// |   2) conflict($value, $rule, $data, $field)          互斥字段（任一存在则当前不能存在）
// |   3) r_if($value, $rule, $data, $field)              条件必填（另一字段等于指定值时）
// |   4) r_with($value, $rule, $data, $field)            任一字段出现时必填
// |   5) r_with_all($value, $rule, $data, $field)        全部字段出现时必填
// |   6) r_without($value, $rule, $data, $field)         任一字段不出现时必填
// |   7) r_without_all($value, $rule, $data, $field)     全部字段不出现时必填
// +----------------------------------------------------------------------

namespace app\parkinglot\validate;

use think\Validate;

class BaseValidator extends Validate
{
    /**
     * 字段存在时才验证（否则跳过）
     *
     * 用法：'mobile' => 'sometimes|regex:1\d{10}'
     *
     * 实际语义：这是一个"标记"规则，仅声明"字段存在时才进入校验"。
     * TP 5.0.24 的 checkItem 已经对 null/空值自动跳过非 require 规则，
     * 因此 sometimes 在这里只需返回 true，让后续规则自然走 TP 默认逻辑。
     */
    public function sometimes($value, $rule, $data, $field)
    {
        return true;
    }

    /**
     * 互斥字段：当前字段存在时，rule 中列出的任一字段都不能存在
     * 用法：'email' => 'conflict:mobile,name'
     */
    public function conflict($value, $rule, $data, $field)
    {
        // 当前字段不存在时直接通过（互斥只在字段出现时生效）
        if (!array_key_exists($field, $data)) {
            return true;
        }
        if (is_string($rule)) {
            $rule = explode(',', $rule);
        }
        if (array_intersect($rule, array_keys($data))) {
            return false;
        }
        return true;
    }

    /**
     * 条件必填：另一字段等于任一指定值时，当前字段必须出现且非空
     * 用法：'mobile' => 'r_if:contact_type,phone,sms'
     */
    public function r_if($value, $rule, $data, $field)
    {
        if (is_string($rule)) {
            $rule = explode(',', $rule);
        }
        $another_field = array_shift($rule);
        if (in_array($data[$another_field] ?? null, $rule)) {
            if (isset($data[$field]) && !empty($value)) return true;
            return false;
        }
        return true;
    }

    /**
     * 任一字段出现时必填（Laravel required_with）
     * 用法：'name' => 'r_with:mobile,email'
     */
    public function r_with($value, $rule, $data, $field)
    {
        if (is_string($rule)) {
            $rule = explode(',', $rule);
        }
        $appear = 0;
        foreach ($rule as $r) {
            if (isset($data[$r])) $appear++;
        }
        if ($appear > 0) {
            if (isset($data[$field]) && !empty($value)) return true;
            return false;
        }
        return true;
    }

    /**
     * 全部字段出现时必填（Laravel required_with_all）
     */
    public function r_with_all($value, $rule, $data, $field)
    {
        if (is_string($rule)) {
            $rule = explode(',', $rule);
        }
        $appear = 0;
        foreach ($rule as $r) {
            if (isset($data[$r])) $appear++;
        }
        if ($appear === count($rule)) {
            if (isset($data[$field]) && !empty($value)) return true;
            return false;
        }
        return true;
    }

    /**
     * 任一字段不出现时必填（Laravel required_without）
     */
    public function r_without($value, $rule, $data, $field)
    {
        if (is_string($rule)) {
            $rule = explode(',', $rule);
        }
        $missing = 0;
        foreach ($rule as $r) {
            if (!isset($data[$r])) $missing++;
        }
        if ($missing > 0 && isset($data[$field]) && !empty($value)) return true;
        return false;
    }

    /**
     * 全部字段不出现时必填（Laravel required_without_all）
     */
    public function r_without_all($value, $rule, $data, $field)
    {
        if (is_string($rule)) {
            $rule = explode(',', $rule);
        }
        $missing = 0;
        foreach ($rule as $r) {
            if (!isset($data[$r])) $missing++;
        }
        if ($missing === count($rule)) {
            if (isset($data[$field]) && !empty($value)) return true;
            return false;
        }
        return true;
    }
}
