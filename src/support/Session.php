<?php
// +----------------------------------------------------------------------
// | think-orm  Standalone Session shim
// +----------------------------------------------------------------------
// | NoOp Session：has=false / get=null / delete noop
// | Validate 的 token 规则将始终失败；用户注入真实 Session 可启用
// +----------------------------------------------------------------------

namespace think;

class Session
{
    public static function has($name, $prefix = null)
    {
        return false;
    }

    public static function get($name, $prefix = null)
    {
        return null;
    }

    public static function delete($name, $prefix = null)
    {
    }
}
