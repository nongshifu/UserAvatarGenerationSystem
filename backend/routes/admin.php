<?php
/**
 * 管理后台接口（鉴权 JWT + role=admin）
 * @var \App\Core\Router $router
 */

use App\Controllers\Admin\AuthController;
use App\Controllers\Admin\DashboardController;
use App\Controllers\Admin\UserController;
use App\Controllers\Admin\KeyController;
use App\Controllers\Admin\OrderController;
use App\Controllers\Admin\PointController;
use App\Controllers\Admin\GenerationController;
use App\Controllers\Admin\AvatarController;
use App\Controllers\Admin\SettingController;
use App\Controllers\Admin\PromptController;
use App\Controllers\Admin\CacheController;

// 管理员登录（无需鉴权）
$router->post('/admin-api/login', [AuthController::class, 'login']);

// JWT 校验中间件
$adminAuth = function (\App\Core\Request $req, \App\Core\Response $res, callable $next): void {
    $token = $req->header('Authorization');
    if (!$token || !str_starts_with($token, 'Bearer ')) {
        $res->error(4002, 'Unauthorized', 401);
        return;
    }
    $payload = \App\Services\AdminAuthService::verify(substr($token, 7));
    if (!$payload || ($payload['role'] ?? '') !== 'admin') {
        $res->error(4002, 'Forbidden', 403);
        return;
    }
    $req->with('admin', $payload);
    $next();
};

$router->group('/admin-api', function (\App\Core\Router $r) use ($adminAuth): void {
    $r->get ('/me',             [AuthController::class,       'me'],     [$adminAuth]);

    $r->get ('/dashboard',      [DashboardController::class, 'index'],  [$adminAuth]);

    // 用户
    $r->get ('/users',          [UserController::class,      'index'],  [$adminAuth]);
    $r->get ('/users/{id}',     [UserController::class,      'show'],   [$adminAuth]);
    $r->put ('/users/{id}',     [UserController::class,      'update'], [$adminAuth]);
    $r->post('/users/{id}/points', [UserController::class,  'adjustPoints'], [$adminAuth]);

    // KEY
    $r->get ('/keys',            [KeyController::class,       'index'],  [$adminAuth]);
    $r->get ('/keys/{id}/avatars', [KeyController::class,    'avatars'],[$adminAuth]);
    $r->get ('/keys/{id}/sub-users', [KeyController::class,  'subUsers'], [$adminAuth]);
    $r->post('/keys/{id}/reset',[KeyController::class,       'reset'],  [$adminAuth]);
    $r->post('/keys/{id}/toggle',[KeyController::class,      'toggle'], [$adminAuth]);

    // 订单
    $r->get ('/orders',          [OrderController::class,     'index'],  [$adminAuth]);
    $r->post('/orders/{id}/audit',  [OrderController::class,  'audit'],  [$adminAuth]);
    $r->post('/orders/{id}/refund', [OrderController::class,  'refund'], [$adminAuth]);

    // 积分套餐 + 流水
    $r->get ('/points/products', [PointController::class,     'products'], [$adminAuth]);
    $r->post('/points/products', [PointController::class,     'storeProduct'], [$adminAuth]);
    $r->put ('/points/products/{id}', [PointController::class,'updateProduct'], [$adminAuth]);
    $r->delete('/points/products/{id}', [PointController::class, 'destroyProduct'], [$adminAuth]);
    $r->get ('/points/logs',     [PointController::class,     'logs'], [$adminAuth]);

    // 生成记录
    $r->get ('/generations',     [GenerationController::class,'index'], [$adminAuth]);
    $r->get ('/generations/{id}',[GenerationController::class,'show'],  [$adminAuth]);

    // 头像
    $r->get ('/avatars',         [AvatarController::class,    'index'], [$adminAuth]);
    $r->get ('/avatars/public',  [AvatarController::class,    'publicList'], [$adminAuth]);
    $r->delete('/avatars/{id}',  [AvatarController::class,    'destroy'], [$adminAuth]);
    $r->post('/avatars/{id}/public', [AvatarController::class, 'togglePublic'], [$adminAuth]);
    $r->post('/avatars/{id}/audit',  [AvatarController::class, 'audit'], [$adminAuth]);

    // 设置
    $r->get ('/settings',        [SettingController::class,   'index'],  [$adminAuth]);
    $r->put ('/settings',        [SettingController::class,   'update'], [$adminAuth]);

    // 提示词管理
    $r->get ('/prompts',               [PromptController::class, 'index'],          [$adminAuth]);
    $r->put ('/prompts/styles/{id}',   [PromptController::class, 'updateStyle'],    [$adminAuth]);
    $r->post('/prompts/styles/{id}/reset', [PromptController::class, 'resetStyle'], [$adminAuth]);
    $r->post('/prompts/reset-all',     [PromptController::class, 'resetAll'],       [$adminAuth]);
    $r->put ('/prompts/template',      [PromptController::class, 'updateTemplate'], [$adminAuth]);
    $r->post('/prompts/template/reset',[PromptController::class, 'resetTemplate'],  [$adminAuth]);
    $r->put ('/prompts/suffix',        [PromptController::class, 'updateSuffix'],   [$adminAuth]);

    // 缓存维护
    $r->post('/cache/flush',          [CacheController::class, 'flush'],        [$adminAuth]);
    $r->post('/cache/clean-uploads',  [CacheController::class, 'cleanUploads'], [$adminAuth]);
});
