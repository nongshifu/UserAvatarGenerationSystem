<?php
declare(strict_types=1);

namespace App\Core;

/**
 * 请求封装
 */
final class Request
{
    private static ?Request $instance = null;
    /** @var array<string,mixed> JSON body 解析缓存 */
    private array $jsonBody = [];
    private bool $jsonParsed = false;

    private function __construct() {}

    /** @var array<string,mixed> 用户态数据（中间件挂载 key/admin 等） */
    private array $userland = [];

    /**
     * 设置用户态数据（中间件用）
     */
    public function with(string $key, mixed $value): void
    {
        $this->userland[$key] = $value;
    }

    /**
     * 读取用户态数据
     */
    public function load(string $key, mixed $default = null): mixed
    {
        return $this->userland[$key] ?? $default;
    }

    public static function capture(): self
    {
        return self::$instance ??= new self();
    }

    public function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public function path(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $uri = parse_url($uri, PHP_URL_PATH) ?: '/';
        return '/' . ltrim($uri, '/');
    }

    /** 所有 query 参数 */
    public function query(): array
    {
        return $_GET;
    }

    public function queryGet(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    /** 所有 POST 表单字段 */
    public function post(): array
    {
        return $_POST;
    }

    public function postGet(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $default;
    }

    /**
     * 合并：JSON body > POST > GET
     * 优先级：JSON body > 表单 > query
     */
    public function all(): array
    {
        return array_merge($_GET, $_POST, $this->jsonBody());
    }

    public function input(string $key, mixed $default = null): mixed
    {
        $all = $this->all();
        return $all[$key] ?? $default;
    }

    /** 仅取指定键 */
    public function only(string ...$keys): array
    {
        $all = $this->all();
        $out = [];
        foreach ($keys as $k) {
            if (array_key_exists($k, $all)) {
                $out[$k] = $all[$k];
            }
        }
        return $out;
    }

    /** 解析 JSON body（仅一次） */
    public function jsonBody(): array
    {
        if ($this->jsonParsed) {
            return $this->jsonBody;
        }
        $this->jsonParsed = true;
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($contentType, 'application/json') !== false) {
            $raw = file_get_contents('php://input');
            if ($raw !== '' && $raw !== false) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $this->jsonBody = $decoded;
                }
            }
        }
        return $this->jsonBody;
    }

    /** 上传文件 */
    public function file(string $key): ?array
    {
        return $_FILES[$key] ?? null;
    }

    public function header(string $name, ?string $default = null): ?string
    {
        $name = strtoupper(preg_replace('/-/', '_', $name) ?? '');
        $serverKey = 'HTTP_' . $name;
        if (isset($_SERVER[$serverKey])) {
            return $_SERVER[$serverKey];
        }
        if ($name === 'CONTENT_TYPE' && isset($_SERVER['CONTENT_TYPE'])) {
            return $_SERVER['CONTENT_TYPE'];
        }
        if ($name === 'CONTENT_LENGTH' && isset($_SERVER['CONTENT_LENGTH'])) {
            return $_SERVER['CONTENT_LENGTH'];
        }
        return $default;
    }

    public function ip(): string
    {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    public function userAgent(): string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? '';
    }
}
