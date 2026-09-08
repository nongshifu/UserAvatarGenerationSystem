<?php
declare(strict_types=1);

namespace App\Core;

/**
 * 响应封装
 */
final class Response
{
    private int $status = 200;
    /** @var array<string,string> */
    private array $headers = [];

    public function setStatus(int $code): self
    {
        $this->status = $code;
        return $this;
    }

    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /** JSON 输出，统一格式 */
    public function json(mixed $data, int $code = 0, string $message = 'success', int $httpStatus = 200): void
    {
        $this->status = $httpStatus;
        $this->header('Content-Type', 'application/json; charset=utf-8');
        $payload = [
            'code'      => $code,
            'message'   => $message,
            'data'      => $data,
            'timestamp' => time(),
        ];
        $this->send(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** 错误响应 */
    public function error(int $code, string $message, int $httpStatus = 400): void
    {
        $this->json(null, $code, $message, $httpStatus);
    }

    /** 纯文本输出 */
    public function text(string $content, int $httpStatus = 200): void
    {
        $this->status = $httpStatus;
        $this->header('Content-Type', 'text/plain; charset=utf-8');
        $this->send($content);
    }

    /** HTML 输出（视图渲染后用） */
    public function html(string $content, int $httpStatus = 200): void
    {
        $this->status = $httpStatus;
        $this->header('Content-Type', 'text/html; charset=utf-8');
        $this->send($content);
    }

    /** 重定向 */
    public function redirect(string $url, int $httpStatus = 302): void
    {
        $this->status = $httpStatus;
        $this->header('Location', $url);
        $this->send('');
    }

    /** 404 */
    public function notFound(string $message = 'Not Found'): void
    {
        $this->error(4004, $message, 404);
    }

    private function send(string $body): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        echo $body;
    }
}
