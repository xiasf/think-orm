<?php
// +----------------------------------------------------------------------
// | think-orm  Standalone Lang shim
// +----------------------------------------------------------------------
// | NoOp 语言包：get 原样返回，has 永远 false
// | Validate.php 内部已去掉所有 Lang:: 调用，本类仅作为兜底
// +----------------------------------------------------------------------

namespace think;

class Lang
{
    /**
     * @param string|null $name
     * @param array       $vars
     * @param string      $lang
     * @return mixed
     */
    public static function get($name = null, array $vars = [], $lang = '')
    {
        return $name;
    }

    /**
     * @param string $name
     * @param string $lang
     * @return bool
     */
    public static function has($name, $lang = '')
    {
        return false;
    }
}
