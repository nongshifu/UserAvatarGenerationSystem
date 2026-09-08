<?php
declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\DB;
use App\Core\View;
use App\Models\Avatar;
use App\Services\SeoService;
use App\Services\SiteSettingService;
use App\Services\UserAuthService;
use App\Services\UserService;

final class HomeController
{
    /** GET / */
    public function index(Request $req, Response $res, array $params): void
    {
        $filter = [
            'style_id' => (int)$req->queryGet('style_id'),
            'color_id' => (int)$req->queryGet('color_id'),
            'shape_id' => (int)$req->queryGet('shape_id'),
        ];
        $avatars = Avatar::randomPublic(12, $filter);
        $dims = UserService::dimensionOptions();

        // 首页统计带（轻量 COUNT，走索引）
        $stats = [
            'avatars' => DB::table('avatars')->where('is_public', 1)->where('audit_status', 'approved')->where('status', 'success')->count(),
            'users'   => DB::table('users')->count(),
            'styles'  => DB::table('styles')->where('status', 1)->count(),
        ];

        $seo = SeoService::page('home', [
            'siteName' => SiteSettingService::get('basic', 'site_name', '头像引擎'),
        ]);

        View::display($res, 'home/index', [
            'title'       => $seo['title'],
            'keywords'    => $seo['keywords'],
            'description' => $seo['description'],
            'ogImage'     => $seo['og_image'],
            'avatars'     => $avatars,
            'styles'      => $dims['styles'],
            'colors'      => $dims['colors'],
            'shapes'      => $dims['shapes'],
            'filter'      => $filter,
            'stats'       => $stats,
            'siteName'    => SiteSettingService::get('basic', 'site_name', '头像引擎'),
            'currentUser' => UserAuthService::currentUser(),
        ]);
    }
}
