<?php
// +----------------------------------------------------------------------
// | think-orm  Standalone Response shim
// +----------------------------------------------------------------------
// | 最小化 HTTP Response：原 TP 5.0.24 在 HttpResponseException 中持有 Response。
// | 独立环境下用户极少真正发 HTTP 响应；这个桩仅满足类型约束。
// | 真实场景请通过 \think\Response::setFactory(callable) 注入完整实现。
// +----------------------------------------------------------------------

namespace think;

class Response
{
    /** @var callable|null 工厂：返回真实 Response 实例 */
    public static $factory;

    /**
     * @var mixed 响应体
     */
    protected $content;

    /**
     * @var int HTTP 状态码
     */
    protected $code = 200;

    /**
     * @var string Content-Type
     */
    protected $contentType = 'text/html';

    /**
     * @var array HTTP 头
     */
    protected $header = [];

    /**
     * 创建实例（或调用注入的 factory）
     */
    public static function create($content = '', $code = 200, array $header = [])
    {
        if (self::$factory) {
            return call_user_func(self::$factory, $content, $code, $header);
        }
        return new static($content, $code, $header);
    }

    public function __construct($content = '', $code = 200, array $header = [])
    {
        $this->content = $content;
        $this->code    = $code;
        $this->header  = $header;
    }

    public function getContent()
    {
        return $this->content;
    }

    public function getCode()
    {
        return $this->code;
    }

    public function getHeader($name = null)
    {
        if ($name === null) {
            return $this->header;
        }
        return $this->header[$name] ?? null;
    }

    public function send()
    {
        if (!headers_sent()) {
            http_response_code($this->code);
            foreach ($this->header as $name => $value) {
                header("$name: $value");
            }
        }
        echo $this->content;
    }
}
