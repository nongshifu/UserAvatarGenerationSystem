<?php
declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Models\Avatar;
use App\Services\SiteSettingService;
use App\Services\UserAuthService;
use App\Services\UserService;

/**
 * 用户控制台
 * - GET /console          概览
 * - GET /console/avatars  我的头像
 * - GET /console/points   积分记录
 * - GET /console/orders   订单记录
 */
final class ConsoleController
{
    private function userOrFail(Response $res): ?\App\Models\User
    {
        $user = UserAuthService::currentUser();
        if (!$user) {
            $res->redirect('/login');
            return null;
        }
        return $user;
    }

    /** GET /console */
    public function index(Request $req, Response $res, array $params): void
    {
        $user = $this->userOrFail($res);
        if (!$user) {
            return;
        }
        $stats = UserService::stats((int)$user->id);
        View::display($res, 'console/index', [
            'title'       => '控制台 - 头像引擎',
            'siteName'    => SiteSettingService::get('basic', 'site_name', '头像引擎'),
            'currentUser' => $user,
            'balance'     => UserService::balance((int)$user->id),
            'stats'       => $stats,
        ]);
    }

    /** GET /console/avatars */
    public function avatars(Request $req, Response $res, array $params): void
    {
        $user = $this->userOrFail($res);
        if (!$user) {
            return;
        }
        $page = max(1, (int)$req->queryGet('page', 1));
        $result = UserService::myAvatars((int)$user->id, $page, 24);
        View::display($res, 'console/avatars', [
            'title'       => '我的头像 - 头像引擎',
            'siteName'    => SiteSettingService::get('basic', 'site_name', '头像引擎'),
            'currentUser' => $user,
            'list'         => $result['list'],
            'pagination'   => $result['pagination'],
        ]);
    }

    /** POST /console/avatars/{id}/delete  用户删除自己的头像 */
    public function deleteAvatar(Request $req, Response $res, array $params): void
    {
        $user = $this->userOrFail($res);
        if (!$user) {
            return;
        }
        $avatar = Avatar::find((int)($params['id'] ?? 0));
        if (!$avatar) {
            $res->error(4004, '头像不存在', 404);
            return;
        }
        // 权限校验：只能删除自己的头像
        if ((int)$avatar->user_id !== (int)$user->id) {
            $res->error(4003, '无权删除该头像', 403);
            return;
        }
        $avatar->deleteWithFiles();
        // AJAX 请求返回 JSON；普通 form 提交则跳回列表
        if (strtolower((string)$req->header('X-Requested-With')) === 'xmlhttprequest') {
            $res->json(['deleted' => true]);
        } else {
            $res->redirect('/console/avatars?deleted=1');
        }
    }

    /** GET /console/points （积分记录，支持搜索） */
    public function points(Request $req, Response $res, array $params): void
    {
        $user = $this->userOrFail($res);
        if (!$user) {
            return;
        }
        $page    = max(1, (int)$req->queryGet('page', 1));
        $filters = [
            'keyword' => trim((string)$req->queryGet('q', '')),
            'type'    => trim((string)$req->queryGet('type', '')),
        ];
        $result = UserService::pointLogs((int)$user->id, $page, 20, $filters);
        View::display($res, 'console/points', [
            'title'       => '积分记录 - 头像引擎',
            'siteName'    => SiteSettingService::get('basic', 'site_name', '头像引擎'),
            'currentUser' => $user,
            'balance'     => UserService::balance((int)$user->id),
            'list'        => $result['list'],
            'pagination'  => $result['pagination'],
            'filters'     => $filters,
            'chartSeries' => UserService::pointDailySeries((int)$user->id, 365),
        ]);
    }

    /** GET /console/generations （生成记录/任务进度，支持搜索） */
    public function generations(Request $req, Response $res, array $params): void
    {
        $user = $this->userOrFail($res);
        if (!$user) {
            return;
        }
        $page    = max(1, (int)$req->queryGet('page', 1));
        $filters = [
            'keyword' => trim((string)$req->queryGet('q', '')),
            'status'  => trim((string)$req->queryGet('status', '')),
        ];
        $result = UserService::myGenerations((int)$user->id, $page, 15, $filters);

        // 风格名映射（带缓存的维度表）
        $styleMap = [];
        foreach (UserService::dimensionOptions()['styles'] as $s) {
            $styleMap[(int)$s['id']] = $s['name'];
        }

        // 批量取本页已完成任务的结果缩略图（预览列用）
        $avatarIds = [];
        foreach ($result['list'] as $r) {
            if (!empty($r['avatar_id'])) {
                $avatarIds[] = (int)$r['avatar_id'];
            }
        }
        $avatarThumbs = [];
        $avatarFulls = [];
        if ($avatarIds !== []) {
            $rows = DB::table('avatars')
                ->select('id', 'result_url', 'result_thumb_url')
                ->whereIn('id', $avatarIds)
                ->all();
            foreach ($rows as $a) {
                $thumb = trim((string)($a['result_thumb_url'] ?? ''));
                $full = trim((string)($a['result_url'] ?? ''));
                $avatarThumbs[(int)$a['id']] = $thumb !== '' ? $thumb : $full;
                $avatarFulls[(int)$a['id']] = $full;
            }
        }

        View::display($res, 'console/generations', [
            'title'        => '生成记录 - 头像引擎',
            'siteName'     => SiteSettingService::get('basic', 'site_name', '头像引擎'),
            'currentUser'  => $user,
            'balance'      => UserService::balance((int)$user->id),
            'list'         => $result['list'],
            'pagination'   => $result['pagination'],
            'filters'      => $filters,
            'styleMap'     => $styleMap,
            'avatarThumbs' => $avatarThumbs,
            'avatarFulls'  => $avatarFulls,
            'genChartSeries' => UserService::generationDailySeries((int)$user->id, 365),
        ]);
    }

    /** GET /console/orders （订单记录，支持搜索） */
    public function orders(Request $req, Response $res, array $params): void
    {
        $user = $this->userOrFail($res);
        if (!$user) {
            return;
        }
        $page    = max(1, (int)$req->queryGet('page', 1));
        $filters = [
            'keyword' => trim((string)$req->queryGet('q', '')),
            'status'  => trim((string)$req->queryGet('status', '')),
        ];
        $result = UserService::myOrders((int)$user->id, $page, 20, $filters);
        View::display($res, 'console/orders', [
            'title'       => '订单记录 - 头像引擎',
            'siteName'    => SiteSettingService::get('basic', 'site_name', '头像引擎'),
            'currentUser' => $user,
            'list'        => $result['list'],
            'pagination'  => $result['pagination'],
            'filters'     => $filters,
        ]);
    }
}
