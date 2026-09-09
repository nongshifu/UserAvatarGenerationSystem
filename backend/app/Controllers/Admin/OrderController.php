<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditLogService;
use App\Services\OrderService;

/**
 * 订单管理
 */
final class OrderController
{
    /**
     * GET /admin-api/orders?status=&pay_method=&keyword=&page=
     * keyword 同时匹配：订单号 / 用户名 / 邮箱 / 手机号
     */
    public function index(Request $req, Response $res, array $params): void
    {
        $q = DB::table('orders');
        if ($status = $req->queryGet('status')) {
            $q->where('status', (string)$status);
        }
        if ($pm = $req->queryGet('pay_method')) {
            $q->where('pay_method', (string)$pm);
        }

        // 关键词：订单号 OR 用户名/邮箱/手机号
        $keyword = trim((string)$req->queryGet('keyword', ''));
        if ($keyword !== '') {
            $orderRows = DB::table('orders')->select('id')->where('order_no', '%' . $keyword . '%', 'like')->all();
            $orderIds  = array_map('intval', array_column($orderRows, 'id'));
            $userRows  = DB::table('users')->select('id')
                ->where('username', '%' . $keyword . '%', 'like')
                ->orWhere('email', '%' . $keyword . '%', 'like')
                ->orWhere('phone', '%' . $keyword . '%', 'like')
                ->all();
            $userIds = array_map('intval', array_column($userRows, 'id'));

            $conds = [];
            $binds = [];
            if (!empty($orderIds)) {
                $phs = [];
                foreach ($orderIds as $i => $id) { $p = ':oid_' . $i; $phs[] = $p; $binds[$p] = $id; }
                $conds[] = 'id IN (' . implode(',', $phs) . ')';
            }
            if (!empty($userIds)) {
                $phs = [];
                foreach ($userIds as $i => $id) { $p = ':uid_' . $i; $phs[] = $p; $binds[$p] = $id; }
                $conds[] = 'user_id IN (' . implode(',', $phs) . ')';
            }
            if (empty($conds)) {
                $q->whereRaw('1 = 0');
            } else {
                $q->whereRaw('(' . implode(' OR ', $conds) . ')', $binds);
            }
        }

        $result = $q->orderBy('id', 'DESC')->paginate(
            (int)$req->queryGet('page', 1),
            (int)$req->queryGet('per_page', 20)
        );
        // 批量附加用户名
        $this->attachUsername($result['list'], 'user_id');
        $res->json($result);
    }

    /** 批量给记录附加 username 字段（避免 N+1） */
    private function attachUsername(array &$list, string $userIdKey): void
    {
        $uids = [];
        foreach ($list as $row) {
            if (!empty($row[$userIdKey])) $uids[(int)$row[$userIdKey]] = true;
        }
        if ($uids) {
            $users = DB::table('users')->select('id', 'username')->whereIn('id', array_keys($uids))->all();
            $map = [];
            foreach ($users as $u) $map[(int)$u['id']] = (string)$u['username'];
            foreach ($list as &$row) {
                $row['username'] = $map[(int)($row[$userIdKey] ?? 0)] ?? '';
            }
            unset($row);
        }
    }

    /**
     * POST /admin-api/orders/{id}/audit
     * body: {action: paid|reject, remark: string}
     */
    public function audit(Request $req, Response $res, array $params): void
    {
        $orderId = (int)$params['id'];
        $action  = (string)$req->input('action', '');
        $remark  = (string)$req->input('remark', '');
        $adminPayload = $req->load('admin');
        $adminId = (int)($adminPayload['user_id'] ?? 0);

        try {
            $order = match ($action) {
                'paid'    => OrderService::auditPaid($orderId, $adminId, $remark),
                'reject'  => OrderService::auditReject($orderId, $adminId, $remark),
                default   => throw new \RuntimeException('不支持的 action', 4001),
            };
        } catch (\RuntimeException $e) {
            $res->error((int)$e->getCode() ?: 4001, $e->getMessage());
            return;
        } catch (\Throwable $e) {
            $res->error(5000, $e->getMessage(), 500);
            return;
        }
        AuditLogService::record($req, $adminId, 'order.audit', 'order', $orderId, "action={$action}");
        $res->json($order->toArray());
    }

    /**
     * POST /admin-api/orders/{id}/refund
     */
    public function refund(Request $req, Response $res, array $params): void
    {
        $orderId = (int)$params['id'];
        $adminPayload = $req->load('admin');
        $adminId = (int)($adminPayload['user_id'] ?? 0);
        try {
            $order = OrderService::refund($orderId, $adminId);
        } catch (\RuntimeException $e) {
            $res->error((int)$e->getCode() ?: 4001, $e->getMessage());
            return;
        } catch (\Throwable $e) {
            $res->error(5000, $e->getMessage(), 500);
            return;
        }
        AuditLogService::record($req, $adminId, 'order.refund', 'order', $orderId, '退款');
        $res->json($order->toArray());
    }
}
