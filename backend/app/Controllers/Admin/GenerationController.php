<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\DB;
use App\Core\Request;
use App\Core\Response;

/**
 * 生成记录管理
 */
final class GenerationController
{
    /**
     * GET /admin-api/generations?keyword=&key_id=&status=&page=
     * keyword 匹配用户名 / 邮箱 / 手机号
     */
    public function index(Request $req, Response $res, array $params): void
    {
        $q = DB::table('generation_records');
        if ($kid = $req->queryGet('key_id')) {
            $q->where('key_id', (int)$kid);
        }
        if ($status = $req->queryGet('status')) {
            $q->where('status', (string)$status);
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
        // 批量附加用户名
        $uids = [];
        foreach ($result['list'] as $row) {
            if (!empty($row['user_id'])) $uids[(int)$row['user_id']] = true;
        }
        if ($uids) {
            $users = DB::table('users')->select('id', 'username')->whereIn('id', array_keys($uids))->all();
            $map = [];
            foreach ($users as $u) $map[(int)$u['id']] = (string)$u['username'];
            foreach ($result['list'] as &$row) {
                $row['username'] = $map[(int)($row['user_id'] ?? 0)] ?? '';
            }
            unset($row);
        }
        $res->json($result);
    }

    /**
     * GET /admin-api/generations/{id}
     */
    public function show(Request $req, Response $res, array $params): void
    {
        $row = DB::table('generation_records')->where('id', (int)$params['id'])->first();
        if (!$row) {
            $res->error(4004, '记录不存在', 404);
            return;
        }
        $res->json($row);
    }
}
