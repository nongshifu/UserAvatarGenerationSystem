<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\PointService;

/**
 * 用户管理
 */
final class UserController
{
    /**
     * GET /admin-api/users?keyword=&role=&status=&page=&per_page=
     */
    public function index(Request $req, Response $res, array $params): void
    {
        $q = DB::table('users');
        $kw = trim((string)$req->queryGet('keyword', ''));
        if ($kw !== '') {
            // 匹配用户名 / 邮箱 / 手机号
            $q->whereRaw('(username LIKE :ukw OR email LIKE :ekw OR phone LIKE :pkw)', [
                ':ukw' => '%' . $kw . '%',
                ':ekw' => '%' . $kw . '%',
                ':pkw' => '%' . $kw . '%',
            ]);
        }
        if ($role = $req->queryGet('role')) {
            $q->where('role', (string)$role);
        }
        if ($req->queryGet('status') !== null) {
            $q->where('status', (int)$req->queryGet('status'));
        }
        $result = $q->orderBy('id', 'DESC')->paginate(
            (int)$req->queryGet('page', 1),
            (int)$req->queryGet('per_page', 20)
        );
        // 隐藏密码字段
        foreach ($result['list'] as &$u) {
            unset($u['password']);
        }
        $res->json($result);
    }

    /**
     * GET /admin-api/users/{id}
     */
    public function show(Request $req, Response $res, array $params): void
    {
        $user = User::find((int)$params['id']);
        if (!$user) {
            $res->error(4004, '用户不存在', 404);
            return;
        }
        $arr = $user->toArray();
        unset($arr['password']);
        $res->json($arr);
    }

    /**
     * PUT /admin-api/users/{id}
     * body: nickname, avatar, role, status, phone, email, password(可选)
     */
    public function update(Request $req, Response $res, array $params): void
    {
        $user = User::find((int)$params['id']);
        if (!$user) {
            $res->error(4004, '用户不存在', 404);
            return;
        }
        $data = $req->only('nickname', 'avatar', 'role', 'status', 'phone', 'email');

        // 邮箱校验
        $email = trim((string)($data['email'] ?? ''));
        if ($email !== '') {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $res->error(4001, '邮箱格式不正确');
                return;
            }
            $dup = DB::table('users')->where('email', $email)->where('id', (int)$user->id, '!=')->first();
            if ($dup) {
                $res->error(4001, '该邮箱已被其他账号使用');
                return;
            }
        }
        // 角色合法性
        if (isset($data['role']) && !in_array((string)$data['role'], ['admin', 'developer', 'user'], true)) {
            $res->error(4001, '角色不合法');
            return;
        }
        // 密码（如传了 password 字段）
        $newPwd = (string)$req->input('password', '');
        if ($newPwd !== '' && strlen($newPwd) < 6) {
            $res->error(4001, '密码至少 6 位');
            return;
        }

        $user->fill($data);
        if ($newPwd !== '') {
            $user->setAttribute('password', password_hash($newPwd, PASSWORD_BCRYPT));
        }
        $user->save();
        AuditLogService::record($req, (int)$req->load('admin')['user_id'], 'user.update', 'user', (int)$params['id'], '更新用户');
        $res->json(['id' => (int)$user->id]);
    }

    /**
     * POST /admin-api/users/{id}/points
     * body: {type: add|sub, amount: int, remark: string}
     */
    public function adjustPoints(Request $req, Response $res, array $params): void
    {
        $userId = (int)$params['id'];
        $type   = (string)$req->input('type', '');
        $amount = (int)$req->input('amount', 0);
        $remark = (string)$req->input('remark', '');
        $adminPayload = $req->load('admin');
        $adminId = (int)($adminPayload['user_id'] ?? 0);

        if ($amount <= 0 || !in_array($type, ['add', 'sub'], true)) {
            $res->error(4001, '参数错误');
            return;
        }
        $user = User::find($userId);
        if (!$user) {
            $res->error(4004, '用户不存在', 404);
            return;
        }
        try {
            if ($type === 'add') {
                $newBal = PointService::recharge($userId, $amount, 0, 'admin_add', '管理员调整：' . $remark);
            } else {
                $newBal = PointService::refund($userId, $amount, 0, 'admin_sub', '管理员扣减：' . $remark);
            }
        } catch (\Throwable $e) {
            $res->error(5000, $e->getMessage(), 500);
            return;
        }
        AuditLogService::record($req, $adminId, 'user.adjust_points', 'user', $userId, "type={$type} amount={$amount}");
        $res->json(['balance' => $newBal]);
    }
}
