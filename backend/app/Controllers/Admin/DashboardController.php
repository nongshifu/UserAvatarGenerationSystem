<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\DB;
use App\Core\Request;
use App\Core\Response;

/**
 * 仪表盘统计
 */
final class DashboardController
{
    public function index(Request $req, Response $res, array $params): void
    {
        $today = date('Y-m-d');
        $stat = [
            'today_generate'  => (int)DB::table('generation_records')->where('created_at', $today, '>=')->count(),
            'today_register'  => (int)DB::table('users')->where('created_at', $today, '>=')->count(),
            'today_recharge'  => (float)DB::table('orders')->where('status', 'paid')->where('paid_at', $today, '>=')->value('amount') ?? 0,
            'avatar_pool_total' => (int)DB::table('avatars')->count(),
            'pending_orders'  => (int)DB::table('orders')->where('status', 'pending')->count(),
            'pending_audit'   => (int)DB::table('avatars')->where('audit_status', 'pending')->count(),
            'user_total'      => (int)DB::table('users')->count(),
        ];

        // 近 90 天每日序列（前端本地切换 7/30/90 范围，无需重新请求）
        $days = 90;
        $from = date('Y-m-d 00:00:00', strtotime('-' . ($days - 1) . ' days'));

        // 连续日期骨架（补齐无数据的日期）
        $dates = [];
        for ($t = strtotime(date('Y-m-d', strtotime($from))); $t <= strtotime($today); $t = strtotime('+1 day', $t)) {
            $dates[date('Y-m-d', $t)] = true;
        }
        $fill = function (array $rows, array $defaults) use ($dates): array {
            $byDay = [];
            foreach ($rows as $r) {
                $byDay[(string)$r['d']] = $r;
            }
            $out = [];
            foreach (array_keys($dates) as $d) {
                $row = ['date' => $d];
                foreach ($defaults as $k => $zero) {
                    $row[$k] = isset($byDay[$d]) ? (is_float($zero) ? (float)$byDay[$d][$k] : (int)$byDay[$d][$k]) : $zero;
                }
                $out[] = $row;
            }
            return $out;
        };

        // 生成记录：每日总数 / 成功 / 失败
        $series['generations'] = $fill(DB::raw(
            "SELECT DATE(created_at) AS d, COUNT(*) AS total,
                    COALESCE(SUM(status = 'success'), 0) AS success,
                    COALESCE(SUM(status = 'failed'), 0) AS failed
             FROM generation_records WHERE created_at >= ?
             GROUP BY DATE(created_at)",
            [$from]
        )->fetchAll(), ['total' => 0, 'success' => 0, 'failed' => 0]);

        // 用户注册：每日注册数
        $series['registers'] = $fill(DB::raw(
            "SELECT DATE(created_at) AS d, COUNT(*) AS total
             FROM users WHERE created_at >= ?
             GROUP BY DATE(created_at)",
            [$from]
        )->fetchAll(), ['total' => 0]);

        // 充值记录：每日已支付金额 / 笔数
        $series['recharges'] = $fill(DB::raw(
            "SELECT DATE(paid_at) AS d, COUNT(*) AS cnt, COALESCE(SUM(amount), 0) AS amount
             FROM orders WHERE status = 'paid' AND paid_at >= ?
             GROUP BY DATE(paid_at)",
            [$from]
        )->fetchAll(), ['cnt' => 0, 'amount' => 0.0]);

        // 全站积分流水：每日收入 / 支出
        $series['points'] = $fill(DB::raw(
            "SELECT DATE(created_at) AS d,
                    COALESCE(SUM(CASE WHEN `change` > 0 THEN `change` ELSE 0 END), 0) AS income,
                    COALESCE(SUM(CASE WHEN `change` < 0 THEN -`change` ELSE 0 END), 0) AS expense
             FROM point_logs WHERE created_at >= ?
             GROUP BY DATE(created_at)",
            [$from]
        )->fetchAll(), ['income' => 0, 'expense' => 0]);

        $res->json(['stat' => $stat, 'series' => $series]);
    }
}
