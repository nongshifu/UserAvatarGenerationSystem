<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Models\Avatar;

final class AvatarController
{
    /**
     * GET /api/v1/avatar/random?style_id=&color_id=&shape_id=&count=
     */
    public function random(Request $req, Response $res, array $params): void
    {
        $count = max(1, min(20, (int)$req->queryGet('count', 1)));
        $list  = Avatar::randomPublic($count, [
            'style_id' => (int)$req->queryGet('style_id'),
            'color_id' => (int)$req->queryGet('color_id'),
            'shape_id'=> (int)$req->queryGet('shape_id'),
        ]);
        $res->json(['list' => $list]);
    }

    /**
     * GET /api/v1/avatar/{id}
     */
    public function show(Request $req, Response $res, array $params): void
    {
        $id     = (int)($params['id'] ?? 0);
        $avatar = Avatar::find($id);
        if (!$avatar) {
            $res->error(4004, 'Avatar not found', 404);
            return;
        }
        $res->json($avatar->toArray());
    }
}
