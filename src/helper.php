<?php
// +----------------------------------------------------------------------
// | think-orm  助手函数（精简版）
// +----------------------------------------------------------------------
// | 只保留 ORM 相关：exception / config / dump / db / model / validate /
// | import / trace / load_relation / collection / debug
// | 已剥离：lang / input / widget / controller / action / url / session /
// |        cookie / cache / request / response / view / json / jsonp / xml /
// |        redirect / abort / halt / token / load_trait / vendor
// +----------------------------------------------------------------------

use think\Config;
use think\Db;
use think\Debug;
use think\Loader;
use think\Model;
use think\Log;

if (!function_exists('exception')) {
    /**
     * 抛出异常处理
     *
     * @param string $msg
     * @param int    $code
     * @param string $exception
     * @throws \think\Exception
     */
    function exception($msg, $code = 0, $exception = '')
    {
        $e = $exception ?: '\\think\\Exception';
        throw new $e($msg, $code);
    }
}

if (!function_exists('config')) {
    /**
     * 获取和设置配置参数
     *
     * @param string|array $name
     * @param mixed        $value
     * @param string       $range
     * @return mixed
     */
    function config($name = '', $value = null, $range = '')
    {
        if (is_null($value) && is_string($name)) {
            return 0 === strpos($name, '?') ? Config::has(substr($name, 1), $range) : Config::get($name, $range);
        }
        return Config::set($name, $value, $range);
    }
}

if (!function_exists('debug')) {
    /**
     * 记录时间（微秒）和内存使用情况
     *
     * @param string         $start
     * @param string         $end
     * @param integer|string $dec
     * @return mixed
     */
    function debug($start, $end = '', $dec = 6)
    {
        if ('' == $end) {
            Debug::remark($start);
        } else {
            return 'm' == $dec ? Debug::getRangeMem($start, $end) : Debug::getRangeTime($start, $end, $dec);
        }
    }
}

if (!function_exists('dump')) {
    /**
     * 浏览器友好的变量输出
     *
     * @param mixed   $var
     * @param bool    $echo
     * @param string  $label
     * @return void|string
     */
    function dump($var, $echo = true, $label = null)
    {
        return Debug::dump($var, $echo, $label);
    }
}

if (!function_exists('model')) {
    /**
     * 实例化Model
     *
     * @param string $name
     * @param string $layer
     * @param bool   $appendSuffix
     * @return \think\Model
     */
    function model($name = '', $layer = 'model', $appendSuffix = false)
    {
        return Loader::model($name, $layer, $appendSuffix);
    }
}

if (!function_exists('validate')) {
    /**
     * 实例化验证器
     *
     * @param string $name
     * @param string $layer
     * @param bool   $appendSuffix
     * @return \think\Validate
     */
    function validate($name = '', $layer = 'validate', $appendSuffix = false)
    {
        return Loader::validate($name, $layer, $appendSuffix);
    }
}

if (!function_exists('db')) {
    /**
     * 实例化数据库类
     *
     * @param string       $name   操作的数据表名称（不含前缀）
     * @param array|string $config 数据库配置参数
     * @param bool         $force  是否强制重新连接
     * @return \think\db\Query
     */
    function db($name = '', $config = [], $force = false)
    {
        return Db::connect($config, $force)->name($name);
    }
}

if (!function_exists('import')) {
    /**
     * 导入所需的类库
     *
     * @param string $class
     * @param string $baseUrl
     * @param string $ext
     * @return bool
     */
    function import($class, $baseUrl = '', $ext = EXT)
    {
        return Loader::import($class, $baseUrl, $ext);
    }
}

if (!function_exists('trace')) {
    /**
     * 记录日志信息
     *
     * @param mixed  $log
     * @param string $level
     * @return void|array
     */
    function trace($log = '[think]', $level = 'log')
    {
        if ('[think]' === $log) {
            return Log::getLog();
        }
        Log::record($log, $level);
    }
}

if (!function_exists('load_relation')) {
    /**
     * 延迟预载入关联查询
     *
     * @param mixed $resultSet
     * @param mixed $relation
     * @return array
     */
    function load_relation($resultSet, $relation)
    {
        $item = current($resultSet);
        if ($item instanceof Model) {
            $item->eagerlyResultSet($resultSet, $relation);
        }
        return $resultSet;
    }
}

if (!function_exists('collection')) {
    /**
     * 数组转换为数据集对象
     *
     * @param array $resultSet
     * @return \think\model\Collection|\think\Collection
     */
    function collection($resultSet)
    {
        $item = current($resultSet);
        if ($item instanceof Model) {
            return \think\model\Collection::make($resultSet);
        }
        return \think\Collection::make($resultSet);
    }
}
