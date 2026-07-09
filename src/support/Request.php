<?php
// +----------------------------------------------------------------------
// | think-orm  Standalone Request shim
// +----------------------------------------------------------------------
// | Provide a minimal Request for Validate::method, Paginator, Loader
// | 默认返回空值；使用者可通过 setInstance / $factory 注入真实 Request
// +----------------------------------------------------------------------

namespace think;

class Request
{
    /**
     * @var self|null
     */
    private static $instance;

    /**
     * @var callable|null 工厂：返回 self 实例
     */
    public static $factory;

    /**
     * @return self
     */
    public static function instance()
    {
        if (self::$instance === null) {
            self::$instance = self::$factory ? call_user_func(self::$factory) : new self();
        }
        return self::$instance;
    }

    /**
     * 注入自定义 Request（传 null 重置回自动实例化）
     *
     * @param self|null $r
     * @return void
     */
    public static function setInstance(self $r = null)
    {
        self::$instance = $r;
    }

    /**
     * 重置实例（仅测试用）
     */
    public static function reset()
    {
        self::$instance = null;
        self::$factory  = null;
    }

    /** 模块名（独立环境固定为空字符串） */
    public function module()
    {
        return '';
    }

    /** 控制器名（独立环境固定为空字符串） */
    public function controller()
    {
        return '';
    }

    /**
     * 请求方法（GET/POST/PUT/DELETE/...）
     *
     * @return string
     */
    public function method()
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    /**
     * 当前 URL 根路径
     *
     * @return string
     */
    public function baseUrl()
    {
        return '/';
    }

    /**
     * 取 param 参数
     *
     * @param string $name
     * @param mixed  $default
     * @return mixed
     */
    public function param($name = '', $default = null)
    {
        return $default;
    }

    /**
     * 取 POST
     */
    public function post($name = '', $default = null)
    {
        return $default;
    }

    /**
     * 取 GET
     */
    public function get($name = '', $default = null)
    {
        return $default;
    }
}
