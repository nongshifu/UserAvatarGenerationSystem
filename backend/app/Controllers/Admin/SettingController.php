<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Services\SiteSettingService;

/**
 * 站点设置
 */
final class SettingController
{
    /**
     * GET /admin-api/settings?group=
     * 不传 group 返回全部分组
     */
    public function index(Request $req, Response $res, array $params): void
    {
        $group = $req->queryGet('group');
        if ($group) {
            $res->json([$group => SiteSettingService::getGroup((string)$group)]);
            return;
        }
        $out = [];
        foreach (['basic', 'seo', 'points', 'api', 'audit', 'mail', 'sms'] as $g) {
            $out[$g] = SiteSettingService::getGroup($g);
        }
        // SEO 配置
        $seoRows = DB::table('seo_settings')->all();
        $out['seo_pages'] = $seoRows;
        $res->json($out);
    }

    /**
     * PUT /admin-api/settings
     * body: {group: string, items: {key: value}}
     */
    public function update(Request $req, Response $res, array $params): void
    {
        $group = (string)$req->input('group', '');
        $items = $req->input('items', []);
        if ($group === '' || !is_array($items)) {
            $res->error(4001, 'group 和 items 必填');
            return;
        }
        SiteSettingService::updateGroup($group, $items);
        $res->json(['updated' => true, 'group' => $group]);
    }
}
