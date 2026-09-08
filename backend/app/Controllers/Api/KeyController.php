<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\DB;
use App\Core\Request;
use App\Core\Response;

final class KeyController
{
    /**
     * GET /api/v1/key/info
     */
    public function info(Request $req, Response $res, array $params): void
    {
        $auth = $req->load('key');
        if (!$auth) {
            $res->error(4002, 'Unauthorized', 401);
            return;
        }
        $key = $auth['key'];
        $res->json([
            'name'        => (string)$key->name,
            'status'      => (int)$key->status,
            'rate_limit'  => (int)$key->rate_limit,
            'daily_limit' => (int)$key->daily_limit,
            'used_today'  => (int)$key->used_today,
        ]);
    }

    /**
     * GET /api/v1/key/avatars
     */
    public function avatars(Request $req, Response $res, array $params): void
    {
        $auth    = $req->load('key');
        if (!$auth) {
            $res->error(4002, 'Unauthorized', 401);
            return;
        }
        $keyId = (int)$auth['key']->id;
        $page    = (int)$req->queryGet('page', 1);
        $perPage = (int)$req->queryGet('per_page', 20);
        $q = DB::table('avatars')->where('key_id', $keyId)->where('status', 'success');
        $subUserId = $req->queryGet('sub_user_id');
        if ($subUserId) {
            $row = DB::table('sub_users')->where('key_id', $keyId)
                ->where('identifier', (string)$subUserId)->first();
            if (!$row) {
                $res->json(['list' => [], 'pagination' => ['total' => 0, 'page' => $page, 'per_page' => $perPage, 'last_page' => 1]]);
                return;
            }
            $q->where('sub_user_id', (int)$row['id']);
        }
        $result = $q->orderBy('id', 'DESC')->paginate($page, $perPage);
        $res->json($result);
    }

    /**
     * GET /api/v1/key/sub-users/{sid}/avatars
     */
    public function subUserAvatars(Request $req, Response $res, array $params): void
    {
        $auth = $req->load('key');
        if (!$auth) {
            $res->error(4002, 'Unauthorized', 401);
            return;
        }
        $keyId = (int)$auth['key']->id;
        $sid   = (string)($params['sid'] ?? '');
        $row   = DB::table('sub_users')->where('key_id', $keyId)->where('identifier', $sid)->first();
        if (!$row) {
            $res->error(4004, 'Sub user not found', 404);
            return;
        }
        $list = DB::table('avatars')
            ->where('sub_user_id', (int)$row['id'])
            ->where('status', 'success')
            ->orderBy('id', 'DESC')
            ->all();
        $res->json(['list' => $list]);
    }

    /**
     * GET /api/v1/user/points
     */
    public function points(Request $req, Response $res, array $params): void
    {
        $auth = $req->load('key');
        if (!$auth) {
            $res->error(4002, 'Unauthorized', 401);
            return;
        }
        $user = $auth['user'];
        $res->json([
            'balance'       => (int)$user->points,
            'total_consume' => (int)$user->total_consume,
        ]);
    }
}
