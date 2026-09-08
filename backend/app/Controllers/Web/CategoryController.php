<?php
declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\SeoService;
use App\Services\SiteSettingService;
use App\Services\UserAuthService;
use App\Services\UserService;

/**
 * 分类页：按 style/color/shape 筛选公共头像
 */
final class CategoryController
{
    /** GET /category, /category/{style} */
    public function index(Request $req, Response $res, array $params): void
    {
        $filter = [
            'style_id' => (int)($params['style_id'] ?? $req->queryGet('style_id')),
            'color_id' => (int)$req->queryGet('color_id'),
            'shape_id' => (int)$req->queryGet('shape_id'),
        ];
        $page    = max(1, (int)$req->queryGet('page', 1));
        $perPage = 24;

        $q = DB::table('avatars')
            ->where('is_public', 1)
            ->where('audit_status', 'approved')
            ->where('status', 'success');
        if ($filter['style_id']) {
            $q->where('style_id', $filter['style_id']);
        }
        if ($filter['color_id']) {
            $q->where('color_id', $filter['color_id']);
        }
        if ($filter['shape_id']) {
            $q->where('shape_id', $filter['shape_id']);
        }
        $result = $q->orderBy('id', 'DESC')->paginate($page, $perPage);
        $dims = UserService::dimensionOptions();

        // 当前筛选名（用于标题）
        $styleName = '';
        if ($filter['style_id']) {
            foreach ($dims['styles'] as $s) {
                if ((int)$s['id'] === $filter['style_id']) {
                    $styleName = $s['name'];
                    break;
                }
            }
        }
        $seo = SeoService::page('category', ['style' => $styleName ?: '全部']);

        View::display($res, 'category/index', [
            'title'        => $seo['title'],
            'keywords'     => $seo['keywords'],
            'description'  => $seo['description'],
            'list'         => $result['list'],
            'pagination'   => $result['pagination'],
            'styles'       => $dims['styles'],
            'colors'       => $dims['colors'],
            'shapes'       => $dims['shapes'],
            'filter'       => $filter,
            'styleName'    => $styleName,
            'siteName'     => SiteSettingService::get('basic', 'site_name', '头像引擎'),
            'currentUser'  => UserAuthService::currentUser(),
        ]);
    }
}
