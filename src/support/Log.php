<?php
// +----------------------------------------------------------------------
// | think-orm  Standalone Log shim
// +----------------------------------------------------------------------
// | 日志策略（按优先级）：
// |   1) PSR-3 logger（通过 setLogger 注入）→ 调用其 log()
// |   2) 文件日志（通过 setLogFile($path) 或 Orm::boot(['log'=>['file'=>...]]) 启用）→ 追加写入
// |   3) 内存缓冲（默认）→ 仅可通过 getLog() 读取
// +----------------------------------------------------------------------

namespace think;

class Log
{
    /**
     * @var object|null PSR-3 LoggerInterface 或同类（含 log() 方法）
     */
    private static $logger;

    /**
     * @var array 没注入 logger、未启用文件日志时的内部缓冲
     */
    private static $logs = [];

    /**
     * @var string|null 文件日志路径（启用时）
     */
    private static $fileLogPath;

    /**
     * @var string 默认级别
     */
    private static $level = 'info';

    /**
     * 注入 PSR-3 Logger
     *
     * @param object|null $logger
     * @return void
     */
    public static function setLogger($logger)
    {
        self::$logger = $logger;
    }

    /**
     * 取出 logger
     *
     * @return object|null
     */
    public static function getLogger()
    {
        return self::$logger;
    }

    /**
     * 启用文件日志：每次 record 都以追加方式写入指定文件
     *
     * 如果传入路径的父目录不存在，会尝试递归创建（0775）。
     *
     * @param string|null $path 绝对路径；传 null 关闭
     * @return void
     */
    public static function setLogFile($path)
    {
        if ($path !== null && $path !== '') {
            $dir = dirname($path);
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
        }
        self::$fileLogPath = $path ?: null;
    }

    /**
     * 记录一条日志
     *
     * @param mixed  $msg
     * @param string $type
     * @return void
     */
    public static function record($msg, $type = 'log')
    {
        $text = is_scalar($msg) ? (string) $msg : var_export($msg, true);

        if (self::$logger !== null && method_exists(self::$logger, 'log')) {
            self::$logger->log(self::toPsrLevel($type), $text);
            return;
        }

        // 内存缓冲始终保留（getLog() 可读）
        self::$logs[$type][] = $msg;

        // 文件日志（如果启用）
        if (self::$fileLogPath !== null) {
            $line = sprintf("[%s] [%s] %s\n", date('Y-m-d H:i:s'), $type, $text);
            // 容错：目录不存在或权限不足不抛异常，仅返回 false
            @file_put_contents(self::$fileLogPath, $line, FILE_APPEND);
        }
    }

    /**
     * 取出缓冲日志
     *
     * @param string|null $type
     * @return array
     */
    public static function getLog($type = null)
    {
        if ($type) {
            return self::$logs[$type] ?? [];
        }
        return self::$logs;
    }

    /**
     * 清空缓冲
     */
    public static function clear()
    {
        self::$logs = [];
    }

    /**
     * 持久化（PSR-3 由 logger 自管；文件日志每次 record 已落盘；这里 noop）
     */
    public static function save()
    {
        return true;
    }

    /**
     * 设置默认级别
     */
    public static function setLevel($level)
    {
        self::$level = $level;
    }

    /**
     * 把 ThinkPHP 风格 level 映射到 PSR-3
     *
     * @param string $type  sql / info / notice / warning / error / debug / log
     * @return string
     */
    private static function toPsrLevel($type)
    {
        static $map = [
            'sql'      => 'debug',
            'debug'    => 'debug',
            'info'     => 'info',
            'log'      => 'info',
            'notice'   => 'notice',
            'warning'  => 'warning',
            'warn'     => 'warning',
            'error'    => 'error',
            'critical' => 'critical',
            'alert'    => 'alert',
            'emergency'=> 'emergency',
        ];
        return $map[$type] ?? self::$level;
    }
}
