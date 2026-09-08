<?php
/**
 * 对外开发者 API（鉴权 X-API-Key）
 * @var \App\Core\Router $router
 */

use App\Controllers\Api\GenerateController;
use App\Controllers\Api\AvatarController;
use App\Controllers\Api\KeyController;
use App\Controllers\Api\UploadController;
use App\Controllers\Api\ConfigController;

// 中间件：API 鉴权 + 限流
$auth = function (\App\Core\Request $req, \App\Core\Response $res, callable $next): void {
    $apiKey = $req->header('X-API-Key');
    if (!$apiKey) {
        $res->error(4002, 'Missing API Key', 401);
        return;
    }
    $verified = \App\Services\ApiAuthService::verify($apiKey);
    if (!$verified) {
        $res->error(4002, 'Invalid API Key', 401);
        return;
    }
    // 限流（按 KEY QPS + 日限额）
    $rateCheck = \App\Services\RateLimitService::check($verified['key']);
    if (!$rateCheck['ok']) {
        $res->error(4029, $rateCheck['reason'], $rateCheck['http_status'] ?? 429);
        return;
    }
    $req->with('key', $verified); // 挂载 {key, user}
    $next();
};

$router->group('/api/v1', function (\App\Core\Router $r) use ($auth): void {
    $r->post('/upload',          [UploadController::class,    'upload']);
    $r->post('/avatar/generate', [GenerateController::class,  'generate'], [$auth]);
    $r->post('/avatar/generate-sync', [GenerateController::class, 'generateSync'], [$auth]);
    $r->get ('/avatar/generate/{record_id}', [GenerateController::class, 'query'], [$auth]);
    $r->get ('/avatar/random',   [AvatarController::class,    'random']);
    $r->get ('/avatar/{id}',     [AvatarController::class,    'show'],    [$auth]);
    $r->get ('/key/avatars',     [KeyController::class,       'avatars'], [$auth]);
    $r->get ('/key/sub-users/{sid}/avatars', [KeyController::class, 'subUserAvatars'], [$auth]);
    $r->get ('/key/info',        [KeyController::class,       'info'],    [$auth]);
    $r->get ('/user/points',     [KeyController::class,       'points'], [$auth]);
    $r->get ('/configs',         [ConfigController::class,    'index']);
});
