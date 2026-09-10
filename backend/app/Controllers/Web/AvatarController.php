<?php
declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\DB;
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

        // 仅拥有者可见：参考原图对比 + 一键重新生成 + 历史生成快捷切换（保护上传者隐私）
        $currentUser = UserAuthService::currentUser();
        $isOwner = $currentUser && (int)($arr['user_id'] ?? 0) === (int)$currentUser->id;
        $originUrl = $isOwner ? trim((string)($arr['origin_url'] ?? '')) : '';
        $historyAvatars = [];
        if ($isOwner) {
            $rows = DB::table('avatars')
                ->select('id', 'result_url', 'result_thumb_url')
                ->where('user_id', (int)$currentUser->id)
                ->orderBy('id', 'DESC')
                ->limit(24)
                ->all();
            foreach ($rows as $h) {
                $historyAvatars[] = [
                    'id'    => (int)$h['id'],
                    'thumb' => trim((string)($h['result_thumb_url'] ?? '')) ?: trim((string)($h['result_url'] ?? '')),
                ];
            }
        }

        View::display($res, 'avatar/detail', [
            'title'          => '头像 #' . $id . ' | ' . SiteSettingService::get('basic', 'site_name', '头像引擎'),
            'keywords'       => $seo['keywords'],
            'description'    => $seo['description'],
            'avatar'         => $arr,
            'originUrl'      => $originUrl,
            'isOwner'        => $isOwner,
            'historyAvatars' => $historyAvatars,
            'siteName'       => SiteSettingService::get('basic', 'site_name', '头像引擎'),
            'currentUser'    => $currentUser,
        ]);
    }
}
