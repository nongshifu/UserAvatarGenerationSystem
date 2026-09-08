<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Models\User;
use App\Services\AdminAuthService;

/**
 * 管理后台登录
 */
final class AuthController
{
    /**
     * POST /admin-api/login
     * body: {username, password}
     */
    public function login(Request $req, Response $res, array $params): void
    {
        $username = (string)$req->input('username', '');
        $password = (string)$req->input('password', '');
        if ($username === '' || $password === '') {
            $res->error(4001, '用户名和密码必填');
            return;
        }

        $user = User::query()
            ->where('username', $username)
            ->where('role', 'admin')
            ->first();
        $userObj = $user ? User::find((int)$user['id']) : null;

        if (!$userObj || !$userObj->checkPassword($password) || !$userObj->isActive()) {
            $res->error(4002, '用户名或密码错误', 401);
            return;
        }

        $token = AdminAuthService::issue($userObj);
        $res->json([
            'token'     => $token,
            'user'      => [
                'id'       => (int)$userObj->id,
                'username' => (string)$userObj->username,
                'role'     => (string)$userObj->role,
            ],
        ]);
    }

    /**
     * GET /admin-api/me
     */
    public function me(Request $req, Response $res, array $params): void
    {
        $payload = $req->load('admin');
        if (!$payload) {
            $res->error(4002, 'Unauthorized', 401);
            return;
        }
        $res->json($payload);
    }
}
