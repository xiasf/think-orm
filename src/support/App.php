<?php
// +----------------------------------------------------------------------
// | think-orm  Standalone App shim
// +----------------------------------------------------------------------
// | 提供 think\App 静态属性 + invokeClass/invokeMethod，避免依赖完整 ThinkPHP
// +----------------------------------------------------------------------

namespace think;

class App
{
    /**
     * @var bool 是否调试模式（Db.php / Query.php 引用）
     */
    public static $debug = false;

    /**
     * @var bool 是否给模型/控制器类名加后缀
     */
    public static $suffix = false;

    /**
     * @var string 应用命名空间（Loader::parseClass 用）
     */
    public static $namespace = 'app';

    /**
     * @var string 当前模块路径（Loader::import 用）
     */
    public static $modulePath = '';

    /**
     * 调用类（不做参数绑定）
     *
     * @param string $class
     * @param array  $vars
     * @return mixed
     */
    public static function invokeClass($class, array $vars = [])
    {
        return new $class;
    }

    /**
     * 调用方法（不做参数绑定）
     *
     * @param callable|array $callable
     * @param array          $vars
     * @return mixed
     */
    public static function invokeMethod($callable, array $vars = [])
    {
        if (is_array($callable)) {
            list($class, $method) = $callable;
            $instance = is_object($class) ? $class : new $class;
            return call_user_func_array([$instance, $method], $vars);
        }
        return call_user_func_array($callable, $vars);
    }
}
