<?php
/**
 * think-orm 程序化入口
 *
 * 用法：
 *   \ThinkOrm\Orm::boot([
 *       'database' => [...],
 *       'paginate' => [...],
 *       'log'      => ['file' => '/var/log/orm.sql.log'],  // 可选：把 SQL 写到文件
 *   ]);
 *
 *   \think\Db::name('user')->select();
 *
 * 可选注入（覆盖 boot 中的 log.file）：
 *   \think\Log::setLogger($psr3Logger);    // PSR-3 LoggerInterface（最高优先级）
 *   \think\Log::setLogFile($path);         // 启用文件日志（次优先级）
 *   \think\Cache::setInstance($psr16);     // PSR-16 CacheInterface
 *   \think\Request::setInstance($r);       // 自定义 Request（用于 Paginator、Validate::method）
 */

namespace ThinkOrm;

use think\App;
use think\Config;
use think\Log;

class Orm
{
    /**
     * @var bool 是否已 boot
     */
    private static $booted = false;

    /**
     * 引导 ORM：定义常量、合并默认配置、写入 Config
     *
     * @param array $config 顶层键：class_suffix / default_validate / action_suffix / database / paginate
     * @return void
     */
    public static function boot(array $config = [])
    {
        if (self::$booted) {
            // 重复 boot：仅合并配置，不重复定义常量
            self::mergeConfig($config);
            return;
        }
        self::$booted = true;

        // 兜底常量（以防用户没 require bootstrap.php 就用了 composer autoload）
        self::defineConstants();

        self::mergeConfig($config);
    }

    /**
     * 递归合并 database/paginate 子键，避免用户子键覆盖掉全部默认
     */
    private static function mergeConfig(array $config)
    {
        $defaults = self::defaults();
        if (isset($config['database']) && is_array($config['database'])) {
            $config['database'] = array_merge($defaults['database'], $config['database']);
        }
        if (isset($config['paginate']) && is_array($config['paginate'])) {
            $config['paginate'] = array_merge($defaults['paginate'], $config['paginate']);
        }
        Config::set(array_merge($defaults, $config));

        // 启用文件 SQL 日志（如果未注入 PSR-3 logger）
        $logCfg = $config['log'] ?? $defaults['log'] ?? [];
        if (!empty($logCfg['file']) && Log::getLogger() === null) {
            Log::setLogFile($logCfg['file']);
        }
    }

    /**
     * 开/关 debug 模式（影响 SQL 日志、trace）
     *
     * @param bool $on
     * @return void
     */
    public static function debug(bool $on = true)
    {
        App::$debug = $on;
        $db = Config::get('database') ?: [];
        $db['debug'] = $on;
        Config::set(['database' => $db]);
    }

    /**
     * 刷新 SQL 日志配置 —— 运行时切换日志文件路径
     *
     * boot() 只在首次调用时设置文件日志，重复 boot 不会覆盖已注入的 logger。
     * 想运行时改日志文件路径，调用本方法。
     *
     * 想换 PSR-3 logger 实例，请直接 \think\Log::setLogger($new)。
     *
     * @param string|null $file 日志文件路径（绝对），传 null 关闭文件日志
     * @return void
     */
    public static function refreshLog(?string $file)
    {
        Log::setLogFile($file);
    }

    /**
     * 重置 boot 状态（仅测试用）
     */
    public static function reset()
    {
        self::$booted = false;
    }

    private static function defineConstants()
    {
        // 仅保留 ORM 运行时真正需要的常量
        defined('DS')            or define('DS', DIRECTORY_SEPARATOR);
        defined('EXT')           or define('EXT', '.php');
        defined('IS_CLI')        or define('IS_CLI', PHP_SAPI === 'cli');
        defined('IS_WIN')        or define('IS_WIN', strpos(PHP_OS, 'WIN') !== false);
        defined('ENV_PREFIX')    or define('ENV_PREFIX', 'PHP_');
        defined('RUNTIME_PATH')  or define('RUNTIME_PATH', sys_get_temp_dir() . DS . 'think-orm' . DS);

        // 字段缓存目录（Query::getFields 在 use_schema=true 时写入此处）
        if (!is_dir(RUNTIME_PATH . 'schema')) {
            @mkdir(RUNTIME_PATH . 'schema', 0777, true);
        }
    }

    private static function defaults()
    {
        return [
            'class_suffix'     => false,
            'default_validate' => '',
            'action_suffix'    => '',
            'database' => [
                // 默认 MySQL 单库
                'type'            => 'mysql',
                'hostname'        => '127.0.0.1',
                'database'        => '',
                'username'        => 'root',
                'password'        => '',
                'hostport'        => '',
                'dsn'             => '',
                'charset'         => 'utf8',
                'prefix'          => '',
                // PDO 构造参数（合并到内置 $this->params 之上）
                // 例：['params' => [PDO::ATTR_PERSISTENT => true]] 开启持久连接
                'params'          => [],
                // Unix socket（仅 mysql；非空时优先于 hostname/hostport）
                'socket'          => '',
                'debug'           => false,
                'deploy'          => 0,
                'rw_separate'     => false,
                'master_num'      => 1,
                'slave_no'        => '',
                // 写后强制读主库（分布式部署时，业务侧有过"写完立刻读不到"的场景）
                'read_master'     => false,
                'fields_strict'   => true,
                'resultset_type'  => 'array',
                'auto_timestamp'  => false,
                'datetime_format' => 'Y-m-d H:i:s',
                'sql_explain'     => false,
                'use_schema'       => false,
                'builder'         => '',
                'query'           => '\\think\\db\\Query',
                'break_reconnect' => false,
            ],
            'paginate' => [
                'type'      => 'bootstrap',
                'var_page'  => 'page',
                'list_rows' => 15,
            ],
            'log' => [
                // 启用后会把所有 SQL 日志追加写入此文件
                // PSR-3 logger（通过 Log::setLogger 注入）优先级高于此
                'file' => null,
            ],
        ];
    }
}
