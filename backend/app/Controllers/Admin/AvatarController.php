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
     * GET /admin-api/avatars/public?style_id=&color_id=&shape_id=&page=
     */
    public function publicList(Request $req, Response $res, array $params): void
    {
        $q = DB::table('avatars')->where('is_public', 1);
        if ($s = $req->queryGet('style_id')) {
            $q->where('style_id', (int)$s);
        }
        if ($c = $req->queryGet('color_id')) {
            $q->where('color_id', (int)$c);
        }
        if ($s2 = $req->queryGet('shape_id')) {
            $q->where('shape_id', (int)$s2);
        }
        $result = $q->orderBy('id', 'DESC')->paginate(
            (int)$req->queryGet('page', 1),
            (int)$req->queryGet('per_page', 24)
        );
        $res->json($result);
    }

    /**
     * GET /admin-api/avatars  全部头像
     */
    public function index(Request $req, Response $res, array $params): void
    {
        $q = DB::table('avatars');
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
