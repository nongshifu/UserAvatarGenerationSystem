<?php
declare(strict_types=1);

namespace App\Core;

/**
 * 路由器
 * - 静态路由
 * - 路径参数 {id}
 * - 支持 GET/POST/PUT/DELETE
 * - 支持分组前缀
 *
 * 用法（routes/web.php / routes/api.php）：
 *   $router->get('/avatar/{id}', [AvatarController::class, 'show']);
 *   $router->group('/api/v1', function (Router $r) { ... });
 */
final class Router
{
    /** @var array<string,array<int,array{method:string,pattern:string,handler:callable|array,middleware?:array}>> */
    private array $routes = [];
    /** @var callable[] 全局中间件 */
    private array $middleware = [];
    private string $groupPrefix = '';
    /** @var callable[] 当前分组中间件 */
    private array $groupMiddleware = [];

    public function addMiddleware(callable $mw): self
    {
        $this->middleware[] = $mw;
        return $this;
    }

    public function group(string $prefix, callable $fn, array $middleware = []): void
    {
        $prevPrefix = $this->groupPrefix;
        $prevMw     = $this->groupMiddleware;
        $this->groupPrefix    = $prevPrefix . $prefix;
        $this->groupMiddleware = array_merge($prevMw, $middleware);
        $fn($this);
        $this->groupPrefix    = $prevPrefix;
        $this->groupMiddleware = $prevMw;
    }

    public function get(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->addRoute('GET', $path, $handler, $middleware);
    }

    public function post(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->addRoute('POST', $path, $handler, $middleware);
    }

    public function put(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->addRoute('PUT', $path, $handler, $middleware);
    }

    public function delete(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->addRoute('DELETE', $path, $handler, $middleware);
    }

    private function addRoute(string $method, string $path, callable|array $handler, array $middleware): void
    {
        $fullPath   = $this->groupPrefix . $path;
        $fullPath   = '/' . trim($fullPath, '/');
        if ($fullPath === '/') {
            // 保留根路径
        }
        $pattern    = $this->compilePattern($fullPath);
        $allMw      = array_merge($this->groupMiddleware, $middleware);
        $this->routes[$method][] = [
            'method'    => $method,
            'pattern'   => $pattern,
            'path'      => $fullPath,
            'handler'   => $handler,
            'middleware'=> $allMw,
        ];
    }

    /**
     * 把 {id} 转成正则
     * /avatar/{id} → #^/avatar/([^/]+)$#
     */
    private function compilePattern(string $path): string
    {
        $pattern = preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', '(?P<$1>[^/]+)', $path) ?? $path;
        return '#^' . $pattern . '$#';
    }

    /**
     * 分发请求
     */
    public function dispatch(Request $request, Response $response): void
    {
        $method = $request->method();
        $path   = $request->path();

        // 处理 HEAD 当作 GET
        if ($method === 'HEAD') {
            $method = 'GET';
        }

        $routes = $this->routes[$method] ?? [];
        foreach ($routes as $route) {
            if (preg_match($route['pattern'], $path, $matches)) {
                // 提取命名捕获组作为路径参数
                $params = array_filter(
                    $matches,
                    fn($k) => is_string($k),
                    ARRAY_FILTER_USE_KEY
                );
                $handler = $route['handler'];

                // 构建中间件链
                $chain = array_merge($this->middleware, $route['middleware'] ?? []);
                $final = function () use ($handler, $params, $request, $response) {
                    $this->invokeHandler($handler, $params, $request, $response);
                };
                foreach (array_reverse($chain) as $mw) {
                    $next     = $final;
                    $final    = function () use ($mw, $next, $request, $response) {
                        $mw($request, $response, $next);
                    };
                }
                $final();
                return;
            }
        }

        $response->notFound("No route for {$method} {$path}");
    }

    /**
     * 调用控制器方法或闭包
     * 控制器方法签名：public function show(Request $req, Response $res, array $params)
     */
    private function invokeHandler(callable|array $handler, array $params, Request $request, Response $response): void
    {
        if (is_array($handler)) {
            [$class, $method] = $handler;
            if (!class_exists($class)) {
                $response->error(5000, "Controller class not found: {$class}", 500);
                return;
            }
            $controller = new $class();
            if (!method_exists($controller, $method)) {
                $response->error(5000, "Method not found: {$class}::{$method}", 500);
                return;
            }
            $controller->{$method}($request, $response, $params);
        } else {
            $handler($request, $response, $params);
        }
    }
}
