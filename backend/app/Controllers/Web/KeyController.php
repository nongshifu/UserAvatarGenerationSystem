<?php
declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\KeyService;
use App\Services\SiteSettingService;
use App\Services\UserAuthService;

/**
 * 用户端 KEY 管理
 * - GET  /console/keys           列表
 * - POST /console/keys           创建
 * - POST /console/keys/{id}/reset
 * - POST /console/keys/{id}/toggle
 */
final class KeyController
{
    /** GET /console/keys */
    public function index(Request $req, Response $res, array $params): void
    {
        $user = UserAuthService::currentUser();
        if (!$user) {
            $res->redirect('/login');
            return;
        }
        $keys = KeyService::listByUser((int)$user->id);
        View::display($res, 'console/keys', [
            'title'       => 'API KEY 管理 - 头像引擎',
            'siteName'    => SiteSettingService::get('basic', 'site_name', '头像引擎'),
            'currentUser' => $user,
            'keys'         => $keys,
            'newKey'       => '',  // 创建/重置后展示完整 key
            'error'        => '',
        ]);
    }

    /** POST /console/keys */
    public function create(Request $req, Response $res, array $params): void
    {
        $user = UserAuthService::currentUser();
        if (!$user) {
            $res->redirect('/login');
            return;
        }
        $name = (string)$req->input('name', '');
        $rateLimit = (int)$req->input('rate_limit', 0);
        $dailyLimit = (int)$req->input('daily_limit', 0);
        $callbackUrl = (string)$req->input('callback_url', '');
        try {
            $created = KeyService::create((int)$user->id, $name, $rateLimit, $dailyLimit, $callbackUrl);
        } catch (\RuntimeException $e) {
            $this->renderKeys($res, $user, '', $e->getMessage());
            return;
        }
        $this->renderKeys($res, $user, $created['api_key'], '');
    }

    /** POST /console/keys/{id}/reset */
    public function reset(Request $req, Response $res, array $params): void
    {
        $user = UserAuthService::currentUser();
        if (!$user) {
            $res->redirect('/login');
            return;
        }
        $keyId = (int)($params['id'] ?? 0);
        try {
            $newKey = KeyService::reset((int)$user->id, $keyId);
        } catch (\RuntimeException $e) {
            $this->renderKeys($res, $user, '', $e->getMessage());
            return;
        }
        $this->renderKeys($res, $user, $newKey, '');
    }

    /** POST /console/keys/{id}/toggle */
    public function toggle(Request $req, Response $res, array $params): void
    {
        $user = UserAuthService::currentUser();
        if (!$user) {
            $res->redirect('/login');
            return;
        }
        $keyId = (int)($params['id'] ?? 0);
        try {
            KeyService::toggle((int)$user->id, $keyId);
        } catch (\RuntimeException $e) {
            $this->renderKeys($res, $user, '', $e->getMessage());
            return;
        }
        $this->renderKeys($res, $user, '', '');
    }

    private function renderKeys(Response $res, \App\Models\User $user, string $newKey, string $error): void
    {
        $keys = KeyService::listByUser((int)$user->id);
        View::display($res, 'console/keys', [
            'title'       => 'API KEY 管理 - 头像引擎',
            'siteName'    => SiteSettingService::get('basic', 'site_name', '头像引擎'),
            'currentUser' => $user,
            'keys'         => $keys,
            'newKey'       => $newKey,
            'error'        => $error,
        ]);
    }
}
