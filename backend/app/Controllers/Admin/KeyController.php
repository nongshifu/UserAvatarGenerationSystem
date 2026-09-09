<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Models\UserKey;

/**
 * 开发者 KEY 管理
 */
final class KeyController
{
    /**
     * GET /admin-api/keys?keyword=&status=&page=
     * keyword 匹配用户名 / 邮箱 / 手机号
     */
    public function index(Request $req, Response $res, array $params): void
    {
        $q = DB::table('user_keys');
        if ($req->queryGet('status') !== null) {
            $q->where('status', (int)$req->queryGet('status'));
        }
        // 用户关键词
        $keyword = trim((string)$req->queryGet('keyword', ''));
        if ($keyword !== '') {
            $userRows = DB::table('users')->select('id')
                ->where('username', '%' . $keyword . '%', 'like')
                ->orWhere('email', '%' . $keyword . '%', 'like')
                ->orWhere('phone', '%' . $keyword . '%', 'like')
                ->all();
            $userIds = array_map('intval', array_column($userRows, 'id'));
            if (empty($userIds)) {
                $q->whereRaw('1 = 0');
            } else {
                $q->whereIn('user_id', $userIds);
            }
        }
        $result = $q->orderBy('id', 'DESC')->paginate(
            (int)$req->queryGet('page', 1),
            (int)$req->queryGet('per_page', 20)
        );
        // 脱敏 + 附加用户名
        $uids = [];
        foreach ($result['list'] as $k) {
            if (!empty($k['user_id'])) $uids[(int)$k['user_id']] = true;
        }
        $userMap = [];
        if ($uids) {
            $users = DB::table('users')->select('id', 'username')->whereIn('id', array_keys($uids))->all();
            foreach ($users as $u) $userMap[(int)$u['id']] = (string)$u['username'];
        }
        foreach ($result['list'] as &$k) {
            $apiKey = (string)$k['api_key'];
            $k['api_key_masked'] = $this->maskKey($apiKey);
            $k['username'] = $userMap[(int)($k['user_id'] ?? 0)] ?? '';
            unset($k['api_key'], $k['api_key_hash']);
        }
        unset($k);
        $res->json($result);
    }

    /**
     * GET /admin-api/keys/{id}/avatars
     */
    public function avatars(Request $req, Response $res, array $params): void
    {
        $keyId = (int)$params['id'];
        $result = DB::table('avatars')
            ->where('key_id', $keyId)
            ->orderBy('id', 'DESC')
            ->paginate((int)$req->queryGet('page', 1), (int)$req->queryGet('per_page', 20));
        $res->json($result);
    }

    /**
     * GET /admin-api/keys/{id}/sub-users
     */
    public function subUsers(Request $req, Response $res, array $params): void
    {
        $keyId = (int)$params['id'];
        $list = DB::table('sub_users')
            ->where('key_id', $keyId)
            ->orderBy('id', 'DESC')
            ->all();
        $res->json(['list' => $list]);
    }

    /**
     * POST /admin-api/keys/{id}/reset  重置 Key
     */
    public function reset(Request $req, Response $res, array $params): void
    {
        $key = UserKey::find((int)$params['id']);
        if (!$key) {
            $res->error(4004, 'Key 不存在', 404);
            return;
        }
        $newKey = 'ark_' . bin2hex(random_bytes(24));
        $key->setAttribute('api_key', $newKey);
        $key->setAttribute('api_key_hash', hash('sha256', $newKey));
        $key->save();
        $res->json(['api_key' => $newKey, 'id' => (int)$key->id]);
    }

    /**
     * POST /admin-api/keys/{id}/toggle  启用/禁用
     */
    public function toggle(Request $req, Response $res, array $params): void
    {
        $key = UserKey::find((int)$params['id']);
        if (!$key) {
            $res->error(4004, 'Key 不存在', 404);
            return;
        }
        $key->setAttribute('status', (int)$key->status === 1 ? 0 : 1);
        $key->save();
        $res->json(['status' => (int)$key->status]);
    }

    private function maskKey(string $apiKey): string
    {
        $len = strlen($apiKey);
        if ($len <= 12) {
            return str_repeat('*', $len);
        }
        return substr($apiKey, 0, 8) . str_repeat('*', $len - 12) . substr($apiKey, -4);
    }
}
