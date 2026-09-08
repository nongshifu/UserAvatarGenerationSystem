<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\RedisClient;
use App\Models\PointLog;

/**
 * 积分服务
 * - 扣减/退还/充值/赠送
 * - 同步 users.points 与 point_logs
 * - 使用 DB 事务保证一致性
 */
final class PointService
{
    /**
     * 预扣积分（生成前调用）
     * @throws \RuntimeException 余额不足
     */
    public static function consume(int $userId, int $cost, int $relatedId, string $remark = ''): int
    {
        return DB::transaction(function () use ($userId, $cost, $relatedId, $remark) {
            // 行锁读取当前余额
            $stmt = DB::raw('SELECT points FROM users WHERE id = ? FOR UPDATE', [$userId]);
            $row  = $stmt->fetch();
            if (!$row) {
                throw new \RuntimeException("User not found: {$userId}", 4004);
            }
            $balance = (int)$row['points'];
            if ($balance < $cost) {
                throw new \RuntimeException('Insufficient points', 4003);
            }
            $newBalance = $balance - $cost;
            DB::raw(
                'UPDATE users SET points = ?, total_consume = total_consume + ? WHERE id = ?',
                [$newBalance, $cost, $userId]
            );
            PointLog::record($userId, 'consume', -$cost, $newBalance, $relatedId, $remark);
            RedisClient::del('user:balance:' . $userId);
            return $newBalance;
        });
    }

    /**
     * 退还积分（生成失败/审核驳回）
     */
    public static function refund(int $userId, int $amount, int $relatedId, string $type = 'refund', string $remark = ''): int
    {
        return DB::transaction(function () use ($userId, $amount, $relatedId, $type, $remark) {
            $stmt = DB::raw('SELECT points FROM users WHERE id = ? FOR UPDATE', [$userId]);
            $row  = $stmt->fetch();
            if (!$row) {
                throw new \RuntimeException("User not found: {$userId}", 4004);
            }
            $balance    = (int)$row['points'];
            $newBalance = $balance + $amount;
            // 退还时 total_consume 应减少（不可为负），用原子 SQL 表达式
            DB::raw(
                'UPDATE users SET points = ?, total_consume = GREATEST(total_consume - ?, 0) WHERE id = ?',
                [$newBalance, $amount, $userId]
            );
            PointLog::record($userId, $type, $amount, $newBalance, $relatedId, $remark);
            RedisClient::del('user:balance:' . $userId);
            return $newBalance;
        });
    }

    /**
     * 充值/赠送（注册赠送、订单支付）
     */
    public static function recharge(int $userId, int $amount, int $relatedId, string $type = 'recharge', string $remark = ''): int
    {
        return DB::transaction(function () use ($userId, $amount, $relatedId, $type, $remark) {
            $stmt = DB::raw('SELECT points FROM users WHERE id = ? FOR UPDATE', [$userId]);
            $row  = $stmt->fetch();
            if (!$row) {
                throw new \RuntimeException("User not found: {$userId}", 4004);
            }
            $balance    = (int)$row['points'];
            $newBalance = $balance + $amount;
            DB::raw(
                'UPDATE users SET points = ?, total_recharge = total_recharge + ? WHERE id = ?',
                [$newBalance, $amount, $userId]
            );
            PointLog::record($userId, $type, $amount, $newBalance, $relatedId, $remark);
            RedisClient::del('user:balance:' . $userId);
            return $newBalance;
        });
    }
}
