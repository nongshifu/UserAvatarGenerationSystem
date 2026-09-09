<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Models\Avatar;
use App\Services\AuditLogService;

/**
 * 头像池管理
 */
final class AvatarController
{
    /**
     * GET /admin-api/avatars/public?...  公共池头像（is_public=1，其余筛选同 index）
     */
    public function publicList(Request $req, Response $res, array $params): void
    {
        $this->listWithFilter($req, $res, true);
    }

    /**
     * GET /admin-api/avatars  全部头像
     * 筛选参数：
     *   scope      all | public | private   公开范围（默认 all）
     *   style_id   int                       风格 ID
     *   keyword    string                    用户搜索（用户名/邮箱/手机号模糊）
     *   date_from  YYYY-MM-DD                创建日期起
     *   date_to    YYYY-MM-DD                创建日期止
     *   page / per_page
     * 返回的每条记录附加 style_name 字段
     */
    public function index(Request $req, Response $res, array $params): void
    {
        $this->listWithFilter($req, $res, false);
    }

    /**
     * 统一筛选查询（内部）
     * @param bool $forcePublic true 时固定 is_public=1（公共池）
     */
    private function listWithFilter(Request $req, Response $res, bool $forcePublic): void
    {
        $q = DB::table('avatars');

        // 公开范围
        if ($forcePublic) {
            $q->where('is_public', 1);
        } else {
            $scope = (string)$req->queryGet('scope', 'all');
            if ($scope === 'public') {
                $q->where('is_public', 1);
            } elseif ($scope === 'private') {
                $q->where('is_public', 0);
            }
        }

        // 风格
        if ($styleId = (int)$req->queryGet('style_id', 0)) {
            $q->where('style_id', $styleId);
        }

        // 用户搜索（用户名 / 邮箱 / 手机号模糊匹配）
        $keyword = trim((string)$req->queryGet('keyword', ''));
        if ($keyword !== '') {
            $userRows = DB::table('users')->select('id')
                ->where('username', '%' . $keyword . '%', 'like')
                ->orWhere('email', '%' . $keyword . '%', 'like')
                ->orWhere('phone', '%' . $keyword . '%', 'like')
                ->all();
            $userIds = array_map('intval', array_column($userRows, 'id'));
            if (empty($userIds)) {
                $q->whereRaw('1 = 0'); // 无匹配用户，直接返回空
            } else {
                $q->whereIn('user_id', $userIds);
            }
        }

        // 日期范围（created_at）
        if ($from = trim((string)$req->queryGet('date_from', ''))) {
            $q->where('created_at', $from . ' 00:00:00', '>=');
        }
        if ($to = trim((string)$req->queryGet('date_to', ''))) {
            $q->where('created_at', $to . ' 23:59:59', '<=');
        }

        // 兼容旧参数
        if ($keyId = $req->queryGet('key_id')) {
            $q->where('key_id', (int)$keyId);
        }
        if ($subId = $req->queryGet('sub_user_id')) {
            $q->where('sub_user_id', (int)$subId);
        }

        $result = $q->orderBy('id', 'DESC')->paginate(
            (int)$req->queryGet('page', 1),
            (int)$req->queryGet('per_page', 24)
        );

        // 批量附加 style_name（避免 N+1，不依赖 join）
        $styleIds = [];
        foreach ($result['list'] as $av) {
            if (!empty($av['style_id'])) {
                $styleIds[(int)$av['style_id']] = true;
            }
        }
        if ($styleIds) {
            $styles = DB::table('styles')->select('id', 'name')->whereIn('id', array_keys($styleIds))->all();
            $styleMap = [];
            foreach ($styles as $s) {
                $styleMap[(int)$s['id']] = (string)$s['name'];
            }
            foreach ($result['list'] as &$av) {
                $av['style_name'] = $styleMap[(int)($av['style_id'] ?? 0)] ?? '';
            }
            unset($av);
        }

        $res->json($result);
    }

    /**
     * DELETE /admin-api/avatars/{id}  删除头像
     */
    public function destroy(Request $req, Response $res, array $params): void
    {
        $avatar = Avatar::find((int)$params['id']);
        if (!$avatar) {
            $res->error(4004, '头像不存在', 404);
            return;
        }
        $avatar->deleteWithFiles();
        $adminPayload = $req->load('admin');
        AuditLogService::record($req, (int)$adminPayload['user_id'], 'avatar.delete', 'avatar', (int)$params['id'], '删除头像');
        $res->json(['deleted' => true]);
    }

    /**
     * POST /admin-api/avatars/{id}/public  切换公共池
     * body: {is_public: 0|1}
     */
    public function togglePublic(Request $req, Response $res, array $params): void
    {
        $avatar = Avatar::find((int)$params['id']);
        if (!$avatar) {
            $res->error(4004, '头像不存在', 404);
            return;
        }
        $avatar->setAttribute('is_public', (int)$req->input('is_public', 0));
        $avatar->save();
        $res->json(['is_public' => (int)$avatar->is_public]);
    }

    /**
     * POST /admin-api/avatars/{id}/audit  审核操作
     * body: {action: approve|reject}
     */
    public function audit(Request $req, Response $res, array $params): void
    {
        $avatar = Avatar::find((int)$params['id']);
        if (!$avatar) {
            $res->error(4004, '头像不存在', 404);
            return;
        }
        $action = (string)$req->input('action', '');
        if (!in_array($action, ['approve', 'reject'], true)) {
            $res->error(4001, 'action 必须为 approve/reject');
            return;
        }
        $avatar->setAttribute('audit_status', $action === 'approve' ? 'approved' : 'rejected');
        if ($action === 'reject') {
            $avatar->setAttribute('is_public', 0);
        }
        $avatar->save();

        $adminPayload = $req->load('admin');
        AuditLogService::record($req, (int)$adminPayload['user_id'], 'avatar.audit', 'avatar', (int)$params['id'], "action={$action}");
        $res->json(['audit_status' => (string)$avatar->audit_status]);
    }
}
