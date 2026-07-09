<?php
// +----------------------------------------------------------------------
// | think-orm  Standalone Debug shim
// +----------------------------------------------------------------------
// | 仅实现 remark / getRangeTime / getRangeMem / dump
// | Connection.php 用于 SQL 执行耗时统计
// +----------------------------------------------------------------------

namespace think;

class Debug
{
    /**
     * @var array 标记点
     */
    private static $marks = [];

    /**
     * 标记一个时间或内存点
     *
     * @param string $name
     * @param string $type 'time' 或 'mem'
     * @return void
     */
    public static function remark($name, $type = 'time')
    {
        self::$marks[$name] = ($type === 'time') ? microtime(true) : memory_get_usage();
    }

    /**
     * 区间耗时（秒，小数）
     *
     * @param string $start
     * @param string $end
     * @param int    $dec  小数位
     * @return float|string
     */
    public static function getRangeTime($start, $end, $dec = 6)
    {
        if (!isset(self::$marks[$start]) || !isset(self::$marks[$end])) {
            return 0;
        }
        return number_format(self::$marks[$end] - self::$marks[$start], $dec);
    }

    /**
     * 区间内存（字节）
     *
     * @param string $start
     * @param string $end
     * @return int
     */
    public static function getRangeMem($start, $end)
    {
        if (!isset(self::$marks[$start]) || !isset(self::$marks[$end])) {
            return 0;
        }
        return self::$marks[$end] - self::$marks[$start];
    }

    /**
     * 取出原始标记
     */
    public static function getMark($name)
    {
        return self::$marks[$name] ?? null;
    }

    /**
     * 浏览器友好的变量输出（与原版签名兼容）
     *
     * @param mixed    $var
     * @param bool     $echo
     * @param string   $label
     * @return string|null
     */
    public static function dump($var, $echo = true, $label = null)
    {
        $out = ($label ? $label . " :\n" : '') . var_export($var, true);
        if ($echo) {
            echo '<pre>' . htmlspecialchars($out, ENT_QUOTES) . '</pre>';
        }
        return $out;
    }
}
