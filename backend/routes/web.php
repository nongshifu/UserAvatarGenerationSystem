<?php
/**
 * 官网 SSR 路由
 * 由 Controllers\Web 渲染 PHP 模板
 * @var \App\Core\Router $router
 */

use App\Controllers\Web\AuthController;
use App\Controllers\Web\AvatarController;
use App\Controllers\Web\CategoryController;
use App\Controllers\Web\ConsoleController;
use App\Controllers\Web\DocController;
use App\Controllers\Web\GenerateController;
use App\Controllers\Web\HomeController;
use App\Controllers\Web\KeyController;
use App\Controllers\Web\PaymentController;
use App\Controllers\Web\ProfileController;
use App\Controllers\Web\RechargeController;
use App\Controllers\Web\SeoController;

// 公开页面
$router->get('/',              [HomeController::class,       'index']);
$router->get('/category',      [CategoryController::class,    'index']);
$router->get('/category/{style_id}', [CategoryController::class, 'index']);
$router->get('/avatar/{id}',   [AvatarController::class,      'show']);
$router->get('/docs',          [DocController::class,         'index']);

// SEO 端点
$router->get('/robots.txt',    [SeoController::class,         'robots']);
$router->get('/sitemap.xml',   [SeoController::class,         'sitemap']);

// 认证
$router->get ('/login',        [AuthController::class,        'loginForm']);
$router->post('/login',        [AuthController::class,        'login']);
$router->get ('/register',     [AuthController::class,        'registerForm']);
$router->post('/register',     [AuthController::class,        'register']);
$router->get ('/logout',      [AuthController::class,        'logout']);

// 找回密码
$router->get ('/forgot',       [AuthController::class,        'forgotForm']);
$router->post('/forgot/code',  [AuthController::class,        'forgotCode']);
$router->post('/forgot/reset', [AuthController::class,        'forgotReset']);

// 控制台（需登录，未登录由控制器自行重定向）
$router->get ('/console',              [ConsoleController::class,  'index']);
$router->get ('/console/profile',      [ProfileController::class,  'index']);
$router->post('/console/profile/update',   [ProfileController::class,  'update']);
$router->post('/console/profile/password', [ProfileController::class,  'password']);
$router->post('/console/profile/phone-code', [ProfileController::class, 'phoneCode']);
$router->post('/console/profile/phone-bind', [ProfileController::class, 'phoneBind']);
$router->post('/console/profile/reset-code',     [ProfileController::class, 'resetCode']);
$router->post('/console/profile/reset-password', [ProfileController::class, 'resetPassword']);
$router->get ('/console/avatars',       [ConsoleController::class,  'avatars']);
$router->get ('/console/generations',   [ConsoleController::class,  'generations']);
$router->get ('/console/points',       [ConsoleController::class,  'points']);
$router->get ('/console/orders',       [ConsoleController::class,  'orders']);
$router->get ('/console/generate',      [GenerateController::class, 'form']);
$router->post('/console/generate',     [GenerateController::class, 'create']);
$router->get ('/console/generate/{id}',[GenerateController::class, 'status']);
$router->get ('/console/keys',         [KeyController::class,       'index']);
$router->post('/console/keys',         [KeyController::class,       'create']);
$router->post('/console/keys/{id}/reset',  [KeyController::class,   'reset']);
$router->post('/console/keys/{id}/toggle', [KeyController::class,   'toggle']);
$router->get ('/console/recharge',        [RechargeController::class, 'index']);
$router->post('/console/recharge',        [RechargeController::class, 'create']);
$router->get ('/console/recharge/return', [RechargeController::class, 'return']);

// 支付回调（无需登录，由网关服务器调用）
$router->post('/pay/notify/wechat', [PaymentController::class, 'wechatNotify']);
$router->get ('/pay/notify/alipay', [PaymentController::class, 'alipayNotify']);
$router->post('/pay/notify/alipay', [PaymentController::class, 'alipayNotify']);
