<?php
declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\SiteSettingService;
use App\Services\UserAuthService;

/**
 * API 文档页（SSR，开发者参考）
 */
final class DocController
{
    /** GET /docs */
    public function index(Request $req, Response $res, array $params): void
    {
        View::display($res, 'docs/index', [
            'title'       => 'API 文档 - 头像引擎',
            'siteName'    => SiteSettingService::get('basic', 'site_name', '头像引擎'),
            'currentUser' => UserAuthService::currentUser(),
        ]);
    }
}
