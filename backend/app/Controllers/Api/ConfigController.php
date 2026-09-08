<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\DB;
use App\Core\Request;
use App\Core\Response;

final class ConfigController
{
    /**
     * GET /api/v1/configs
     */
    public function index(Request $req, Response $res, array $params): void
    {
        $styles = DB::table('styles')->where('status', 1)->orderBy('sort')->all();
        $colors = DB::table('colors')->where('status', 1)->orderBy('sort')->all();
        $shapes = DB::table('shapes')->where('status', 1)->orderBy('sort')->all();
        $res->json(compact('styles', 'colors', 'shapes'));
    }
}
