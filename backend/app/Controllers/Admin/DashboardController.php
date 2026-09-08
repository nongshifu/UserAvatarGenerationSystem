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

        // 近 7 天生成趋势
        $rows = DB::raw(
            "SELECT DATE(created_at) AS d, COUNT(*) AS c
             FROM generation_records
             WHERE created_at >= ?
             GROUP BY DATE(created_at)
             ORDER BY d ASC",
            [date('Y-m-d', strtotime('-6 days')) . ' 00:00:00']
        )->fetchAll();
        $trend = [];
        foreach ($rows as $r) {
            $trend[] = ['date' => $r['d'], 'count' => (int)$r['c']];
        }

        $res->json(['stat' => $stat, 'trend_7d' => $trend]);
    }
}
