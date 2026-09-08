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
     * GET /admin-api/orders?status=&pay_method=&page=
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
        $result = $q->orderBy('id', 'DESC')->paginate(
            (int)$req->queryGet('page', 1),
            (int)$req->queryGet('per_page', 20)
        );
        $res->json($result);
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
