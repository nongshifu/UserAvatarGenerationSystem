<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Models\PointProduct;

/**
 * 积分管理：套餐商品、流水
 */
final class PointController
{
    /**
     * GET /admin-api/points/products
     */
    public function products(Request $req, Response $res, array $params): void
    {
        $result = DB::table('point_products')
            ->orderBy('sort', 'ASC')
            ->paginate((int)$req->queryGet('page', 1), (int)$req->queryGet('per_page', 50));
        $res->json($result);
    }

    /**
     * POST /admin-api/points/products  新建
     */
    public function storeProduct(Request $req, Response $res, array $params): void
    {
        $data = $req->only('name', 'price', 'points', 'bonus_points', 'sort', 'status', 'description');
        if (empty($data['name']) || !isset($data['price'], $data['points'])) {
            $res->error(4001, 'name/price/points 必填');
            return;
        }
        $p = PointProduct::create([
            'name'         => (string)$data['name'],
            'price'        => (float)$data['price'],
            'points'       => (int)$data['points'],
            'bonus_points' => (int)($data['bonus_points'] ?? 0),
            'sort'         => (int)($data['sort'] ?? 0),
            'status'       => (int)($data['status'] ?? 1),
            'description'  => (string)($data['description'] ?? ''),
        ]);
        $res->json(['id' => (int)$p->id]);
    }

    /**
     * PUT /admin-api/points/products/{id}
     */
    public function updateProduct(Request $req, Response $res, array $params): void
    {
        $p = PointProduct::find((int)$params['id']);
        if (!$p) {
            $res->error(4004, '商品不存在', 404);
            return;
        }
        $p->fill($req->only('name', 'price', 'points', 'bonus_points', 'sort', 'status', 'description'));
        $p->save();
        $res->json(['id' => (int)$p->id]);
    }

    /**
     * DELETE /admin-api/points/products/{id}
     */
    public function destroyProduct(Request $req, Response $res, array $params): void
    {
        $p = PointProduct::find((int)$params['id']);
        if (!$p) {
            $res->error(4004, '商品不存在', 404);
            return;
        }
        // 软删除
        $p->setAttribute('deleted_at', date('Y-m-d H:i:s'));
        $p->save();
        $res->json(['deleted' => true]);
    }

    /**
     * GET /admin-api/points/logs?user_id=&type=&page=
     */
    public function logs(Request $req, Response $res, array $params): void
    {
        $q = DB::table('point_logs');
        if ($uid = $req->queryGet('user_id')) {
            $q->where('user_id', (int)$uid);
        }
        if ($type = $req->queryGet('type')) {
            $q->where('type', (string)$type);
        }
        $result = $q->orderBy('id', 'DESC')->paginate(
            (int)$req->queryGet('page', 1),
            (int)$req->queryGet('per_page', 20)
        );
        $res->json($result);
    }
}
