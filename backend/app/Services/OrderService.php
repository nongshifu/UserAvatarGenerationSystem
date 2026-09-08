<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\Log;
use App\Core\RedisClient;
use App\Models\Order;
use App\Models\PointProduct;

/**
 * 订单服务
 * - 创建订单
 * - 审核（确认到账 → 发积分；驳回 → 关闭）
 * - 退款（paid → refunded，扣回积分）
 */
final class OrderService
{
    /**
     * 创建订单
     */
    public static function create(int $userId, int $productId, string $payMethod = 'manual'): Order
    {
        $product = PointProduct::find($productId);
        if (!$product || (int)$product->status !== 1) {
            throw new \RuntimeException('商品不存在或已下架', 4004);
        }
        $arr = $product->toArray();
        $points = (int)$arr['points'] + (int)$arr['bonus_points'];
        $order = Order::create([
            'order_no'     => Order::genOrderNo(),
            'user_id'      => $userId,
            'product_id'   => $productId,
            'product_name' => (string)$arr['name'],
            'amount'       => (float)$arr['price'],
            'points'       => $points,
            'status'       => 'pending',
            'pay_method'   => $payMethod,
            'pay_trade_no' => '',
            'paid_at'      => null,
            'refunded_at'  => null,
            'admin_remark' => '',
        ]);
        return $order;
    }

    /**
     * 后台审核：确认到账，发放积分
     */
    public static function auditPaid(int $orderId, int $adminId, string $remark = ''): Order
    {
        return DB::transaction(function () use ($orderId, $adminId, $remark) {
            $stmt = DB::raw('SELECT * FROM orders WHERE id = ? FOR UPDATE', [$orderId]);
            $row  = $stmt->fetch();
            if (!$row) {
                throw new \RuntimeException('订单不存在', 4004);
            }
            if ($row['status'] !== 'pending') {
                throw new \RuntimeException('订单状态不允许审核', 4001);
            }
            DB::table('orders')->where('id', $orderId)->update([
                'status'       => 'paid',
                'paid_at'      => date('Y-m-d H:i:s'),
                'admin_remark' => $remark,
            ]);
            $balance = PointService::recharge(
                (int)$row['user_id'],
                (int)$row['points'],
                $orderId,
                'recharge',
                '订单 ' . $row['order_no'] . ' 充值'
            );
            Log::info('order paid', ['order_id' => $orderId, 'admin' => $adminId, 'balance' => $balance]);
            return Order::find($orderId) ?? throw new \RuntimeException('更新后订单未找到', 5000);
        });
    }

    /**
     * 驳回：关闭订单，不发积分
     */
    public static function auditReject(int $orderId, int $adminId, string $remark = ''): Order
    {
        return DB::transaction(function () use ($orderId, $adminId, $remark) {
            $stmt = DB::raw('SELECT * FROM orders WHERE id = ? FOR UPDATE', [$orderId]);
            $row  = $stmt->fetch();
            if (!$row) {
                throw new \RuntimeException('订单不存在', 4004);
            }
            if ($row['status'] !== 'pending') {
                throw new \RuntimeException('订单状态不允许驳回', 4001);
            }
            DB::table('orders')->where('id', $orderId)->update([
                'status'       => 'closed',
                'admin_remark' => $remark,
            ]);
            Log::info('order rejected', ['order_id' => $orderId, 'admin' => $adminId]);
            return Order::find($orderId) ?? throw new \RuntimeException('订单未找到', 5000);
        });
    }

    /**
     * 退款：paid → refunded，扣回积分
     * 注意：可能余额不足（用户已消耗），扣到 0 为止，差额记负数流水
     */
    public static function refund(int $orderId, int $adminId): Order
    {
        return DB::transaction(function () use ($orderId, $adminId) {
            $stmt = DB::raw('SELECT * FROM orders WHERE id = ? FOR UPDATE', [$orderId]);
            $row  = $stmt->fetch();
            if (!$row) {
                throw new \RuntimeException('订单不存在', 4004);
            }
            if ($row['status'] !== 'paid') {
                throw new \RuntimeException('仅已支付订单可退款', 4001);
            }
            $userId = (int)$row['user_id'];
            $points = (int)$row['points'];

            // 行锁取当前余额
            $uStmt = DB::raw('SELECT points FROM users WHERE id = ? FOR UPDATE', [$userId]);
            $uRow  = $uStmt->fetch();
            if (!$uRow) {
                throw new \RuntimeException('用户不存在', 4004);
            }
            $current   = (int)$uRow['points'];
            $deduct    = min($current, $points);              // 实际可扣
            $newBal    = $current - $deduct;
            $shortfall = $points - $deduct;                   // 用户已消耗导致无法扣回的部分

            DB::raw(
                'UPDATE users SET points = ?, total_consume = total_consume + ? WHERE id = ?',
                [$newBal, $deduct, $userId]
            );
            // 退款流水（change 为负数；shortfall > 0 时单独记一条备注）
            DB::table('point_logs')->insert([
                'user_id'    => $userId,
                'type'       => 'refund',
                'change'     => -$points,
                'balance'    => $newBal,
                'related_id' => $orderId,
                'remark'     => '订单 ' . $row['order_no'] . ' 退款' . ($shortfall > 0 ? "（余额不足，差额 {$shortfall} 未扣）" : ''),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            RedisClient::del('user:balance:' . $userId);

            DB::table('orders')->where('id', $orderId)->update([
                'status'      => 'refunded',
                'refunded_at' => date('Y-m-d H:i:s'),
            ]);
            Log::info('order refunded', ['order_id' => $orderId, 'admin' => $adminId, 'deduct' => $deduct, 'shortfall' => $shortfall]);
            return Order::find($orderId) ?? throw new \RuntimeException('订单未找到', 5000);
        });
    }

    /**
     * 取用户订单列表
     */
    public static function userOrders(int $userId, int $page = 1, int $perPage = 20): array
    {
        return DB::table('orders')
            ->where('user_id', $userId)
            ->orderBy('id', 'DESC')
            ->paginate($page, $perPage);
    }
}

