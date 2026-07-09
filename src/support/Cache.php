<?php
// +----------------------------------------------------------------------
// | think-orm  Standalone Cache shim
// +----------------------------------------------------------------------
// | NoOp 缓存：默认所有操作静默失败；可通过 setInstance 注入 PSR-16 或同类对象
// | 重要：Query.php 中 lazyWrite 会调用 Cache::$type($guid, $step)
// |       这里用 __callStatic 兜底，避免致命错误
// +----------------------------------------------------------------------

namespace think;

class Cache
{
    /**
     * @var object|null PSR-16 CacheInterface 或兼容对象
     */
    private static $handler;

    /**
     * @var callable|null 工厂
     */
    public static $factory;

    /**
     * 注入真实缓存处理对象（PSR-16 或同 API）
     *
     * @param object|null $handler
     * @return void
     */
    public static function setInstance($handler)
    {
        self::$handler = $handler;
    }

    /**
     * 取出 handler
     *
     * @return object|null
     */
    public static function getInstance()
    {
        if (self::$handler === null && self::$factory !== null) {
            self::$handler = call_user_func(self::$factory);
        }
        return self::$handler;
    }

    /**
     * 取缓存
     */
    public static function get($name, $default = false)
    {
        $h = self::getInstance();
        if ($h && method_exists($h, 'get')) {
            return $h->get($name, $default);
        }
        return $default;
    }

    /**
     * 写缓存
     */
    public static function set($name, $value, $expire = null)
    {
        $h = self::getInstance();
        if ($h && method_exists($h, 'set')) {
            return $h->set($name, $value, is_int($expire) ? $expire : 0);
        }
        return false;
    }

    /**
     * 是否存在
     */
    public static function has($name)
    {
        $h = self::getInstance();
        if ($h && method_exists($h, 'has')) {
            return (bool) $h->has($name);
        }
        return false;
    }

    /**
     * 删除（旧 API）
     */
    public static function rm($name)
    {
        return self::delete($name);
    }

    /**
     * 删除（PSR-16 风格）
     */
    public static function delete($name)
    {
        $h = self::getInstance();
        if ($h && method_exists($h, 'delete')) {
            return $h->delete($name);
        }
        return false;
    }

    /**
     * 清空
     */
    public static function clear($tag = null)
    {
        $h = self::getInstance();
        if ($h && method_exists($h, 'clear')) {
            return $h->clear();
        }
        return false;
    }

    /**
     * 取标签代理（链式 set）
     *
     * @param string $name
     * @return object
     */
    public static function tag($name)
    {
        $h = self::getInstance();
        if ($h && method_exists($h, 'tag')) {
            return $h->tag($name);
        }
        // NoOp tag wrapper：所有方法 __call 返回 null，避免 Cache::tag()->set(...) 链式调用崩溃
        return new class {
            public function __call($m, $a)
            {
                return null;
            }
        };
    }

    /**
     * 兜底动态方法（Query.php 中 lazyWrite 的 Cache::$type($guid, $step)）
     *
     * @param string $method
     * @param array  $args
     * @return mixed
     */
    public static function __callStatic($method, $args)
    {
        $h = self::getInstance();
        if ($h && method_exists($h, $method)) {
            return call_user_func_array([$h, $method], $args);
        }
        // 无 handler 时：返回 step 值（lazyWrite 第二参数），让流程继续
        return isset($args[1]) ? $args[1] : (isset($args[0]) ? $args[0] : null);
    }
}
