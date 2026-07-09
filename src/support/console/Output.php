<?php
// +----------------------------------------------------------------------
// | think-orm  Standalone console Output shim
// +----------------------------------------------------------------------
// | 原 TP 5.0.24 的 think\console\Output 用于命令行输出与日志。
// | 独立环境基本不需要；这里仅提供 write/writeln 占位以满足类型约束。
// +----------------------------------------------------------------------

namespace think\console;

class Output
{
    /**
     * 写一行（不带换行）
     */
    public function write($message, $newline = false)
    {
        echo $message;
        if ($newline) echo "\n";
    }

    /**
     * 写一行（带换行）
     */
    public function writeln($message)
    {
        $this->write($message, true);
    }
}
