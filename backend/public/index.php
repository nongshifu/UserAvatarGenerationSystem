<?php
/**
 * 后端入口文件
 * 所有请求经 Nginx rewrite 转发至此
 *
 * Nginx 配置：
 *   location / { try_files $uri $uri/ /index.php?$query_string; }
 *   location ~ \.php$ { fastcgi_pass unix:/tmp/php-cgi-74.sock; ... }
 */
declare(strict_types=1);

// 1. 定义根路径常量
define('ROOT_PATH', dirname(__DIR__)); // backend/

// 2. 自动加载
//    App\Core\*  → backend/core/   （框架核心类）
//    App\*       → backend/app/    （业务类：Models/Services/Controllers/Jobs）
spl_autoload_register(function (string $class): void {
    if (str_starts_with($class, 'App\\Core\\')) {
        $relative = substr($class, strlen('App\\Core\\'));
        $file = ROOT_PATH . '/core/' . str_replace('\\', '/', $relative) . '.php';
        if (is_file($file)) {
            require $file;
        }
        return;
    }
    if (str_starts_with($class, 'App\\')) {
        $relative = substr($class, strlen('App\\'));
        $file = ROOT_PATH . '/app/' . str_replace('\\', '/', $relative) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

// 3. 错误处理（生产环境关闭 display_errors）
$isDebug = (bool)getenv('APP_DEBUG');
error_reporting(E_ALL);
ini_set('display_errors', $isDebug ? '1' : '0');
ini_set('log_errors', '1');

// 4. 全局异常处理
set_exception_handler(function (\Throwable $e) use ($isDebug): void {
    \App\Core\Log::error('uncaught: ' . $e->getMessage(), [
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    $msg = $isDebug ? $e->getMessage() : 'Server Error';
    echo json_encode([
        'code'      => 5000,
        'message'   => $msg,
        'data'      => null,
        'timestamp' => time(),
    ], JSON_UNESCAPED_UNICODE);
});

// 5. 载入路由
$router = new \App\Core\Router();
require ROOT_PATH . '/routes/web.php';   // 官网页面 SSR 路由
require ROOT_PATH . '/routes/api.php';   // 对外开发者 API
require ROOT_PATH . '/routes/admin.php'; // 管理后台接口

// 6. 分发
$request  = \App\Core\Request::capture();
$response = new \App\Core\Response();
$router->dispatch($request, $response);
