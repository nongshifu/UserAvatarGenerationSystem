<?php
declare(strict_types=1);

namespace App\Core;

/**
 * PHP 模板视图
 * - 模板放 backend/app/Views/{name}.php
 * - 变量自动转义（htmlspecialchars），需要原样输出用 {!! $var !!}
 *
 * 用法：
 *   View::make('home/index', ['title' => '首页'])
 *   View::raw('home/index', $data)  // 不自动转义
 */
final class View
{
    /** 视图根目录 */
    private static string $viewPath = '';

    private static function path(): string
    {
        return self::$viewPath ?: (ROOT_PATH . '/app/Views');
    }

    /**
     * 渲染并返回 HTML
     * @param string $template 如 'home/index'（对应 Views/home/index.php）
     * @param array<string,mixed> $data
     */
    public static function make(string $template, array $data = []): string
    {
        $file = self::resolveTemplate($template);
        return self::render($file, $data);
    }

    /**
     * 直接输出 HTML
     */
    public static function display(Response $response, string $template, array $data = [], int $status = 200): void
    {
        $html = self::make($template, $data);
        $response->html($html, $status);
    }

    private static function resolveTemplate(string $template): string
    {
        $template = str_replace('.', '/', $template);
        $file = self::path() . '/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("View not found: {$template}", 500);
        }
        return $file;
    }

    private static function render(string $file, array $data): string
    {
        // 变量注入作用域
        $e = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
        extract($data, EXTR_SKIP);
        ob_start();
        include $file;
        return (string)ob_get_clean();
    }
}
