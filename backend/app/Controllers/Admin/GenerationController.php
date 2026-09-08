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
     * GET /admin-api/generations?user_id=&key_id=&status=&page=
     */
    public function index(Request $req, Response $res, array $params): void
    {
        $q = DB::table('generation_records');
        if ($uid = $req->queryGet('user_id')) {
            $q->where('user_id', (int)$uid);
        }
        if ($kid = $req->queryGet('key_id')) {
            $q->where('key_id', (int)$kid);
        }
        if ($status = $req->queryGet('status')) {
            $q->where('status', (string)$status);
        }
        $result = $q->orderBy('id', 'DESC')->paginate(
            (int)$req->queryGet('page', 1),
            (int)$req->queryGet('per_page', 20)
        );
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
