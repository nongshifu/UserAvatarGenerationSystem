<?php
declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Models\Avatar;
use App\Services\SeoService;
use App\Services\SiteSettingService;
use App\Services\UserAuthService;

/**
 * 头像详情页（SSR，利于收录）
 */
final class AvatarController
{
    /** GET /avatar/{id} */
    public function show(Request $req, Response $res, array $params): void
    {
        $id = (int)($params['id'] ?? 0);
        $avatar = Avatar::find($id);
        if (!$avatar) {
            $res->notFound('头像不存在');
            return;
        }
        $arr = $avatar->toArray();
        // 浏览量 +1（不阻塞渲染）
        $avatar->incrementViews();

        $seo = SeoService::page('avatar', ['id' => (string)$id]);

        View::display($res, 'avatar/detail', [
            'title'        => '头像 #' . $id . ' | ' . SiteSettingService::get('basic', 'site_name', '头像引擎'),
            'keywords'     => $seo['keywords'],
            'description'  => $seo['description'],
            'avatar'       => $arr,
            'siteName'     => SiteSettingService::get('basic', 'site_name', '头像引擎'),
            'currentUser'  => UserAuthService::currentUser(),
        ]);
    }
}
