<?php
/**
 * 卡死任务修复脚本（独立运行，不影响常驻 Worker）
 *
 * 用法：
 *   php backend/bin/repair.php
 *
 * 逻辑：
 *   1. processing 超过 10 分钟（Worker 中途死掉）→ 重置为 pending
 *   2. pending 超过 2 分钟（队列消息丢失）且有原图地址 → 重新入队
 *   3. pending 超过 2 分钟但缺原图地址（迁移前老任务）→ 标记失败并自动退积分
 *
 * 建议：宝塔计划任务每 5 分钟执行一次，命令：
 *   sudo -u www /www/server/php/83/bin/php /www/wwwroot/myradar.cn/backend/bin/repair.php
 */
declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__)); // backend/

spl_autoload_register(function (string $class): void {
    if (str_starts_with($class, 'App\\Core\\')) {
        $file = ROOT_PATH . '/core/' . str_replace('\\', '/', substr($class, strlen('App\\Core\\'))) . '.php';
    } elseif (str_starts_with($class, 'App\\')) {
        $file = ROOT_PATH . '/app/' . str_replace('\\', '/', substr($class, strlen('App\\'))) . '.php';
    } else {
        return;
    }
    if (is_file($file)) {
        require $file;
    }
});

use App\Core\DB;
use App\Core\Log;
use App\Core\RedisClient;
use App\Services\AvatarGenerateService;
use App\Services\PointService;

$queue    = AvatarGenerateService::QUEUE_NAME;
$requeued = 0;
$refunded = 0;
$candidates = [];

// 1) processing 超过 10 分钟：Worker 中途死掉，先重置为 pending（纳入本次处置）
$stuckProcessing = DB::table('generation_records')
    ->where('status', 'processing')
    ->where('updated_at', date('Y-m-d H:i:s', time() - 600), '<')
    ->all();
foreach ($stuckProcessing as $r) {
    DB::raw('UPDATE generation_records SET status = "pending", updated_at = NOW() WHERE id = :id AND status = "processing"',
        [':id' => (int)$r['id']]);
    Log::warning('repair: reset stale processing -> pending', ['record_id' => (int)$r['id']]);
    $candidates[] = $r;
}

// 2) pending 超过 2 分钟：队列消息大概率已丢失（Redis 重启/Worker 未运行）
$stuckPending = DB::table('generation_records')
    ->where('status', 'pending')
    ->where('updated_at', date('Y-m-d H:i:s', time() - 120), '<')
    ->all();
foreach ($stuckPending as $r) {
    $candidates[] = $r;
}

// 3) 逐个处置：有原图地址 → 重新入队；缺原图（迁移前老任务）→ 标记失败并退积分
foreach ($candidates as $r) {
    $rid = (int)$r['id'];
    if (empty($r['origin_image_url'])) {
        DB::raw(
            'UPDATE generation_records SET status = "failed", error_msg = :msg, updated_at = NOW() WHERE id = :id AND status = "pending"',
            [':id' => $rid, ':msg' => '任务缺少原图地址，无法恢复（系统自动退积分）']
        );
        try {
            PointService::refund(
                (int)$r['user_id'], (int)$r['cost_points'], $rid,
                'generate_refund', '卡死任务无法恢复，自动退还'
            );
            $refunded++;
            echo "[repair] record #{$rid} 无原图 → 标记失败并退还 " . (int)$r['cost_points'] . " 积分\n";
        } catch (\Throwable $e) {
            Log::error('repair refund failed', ['record_id' => $rid, 'err' => $e->getMessage()]);
            echo "[repair] record #{$rid} 退款失败：" . $e->getMessage() . "\n";
        }
        continue;
    }
    RedisClient::push($queue, ['record_id' => $rid]);
    Log::warning('repair: re-enqueued stuck task', ['record_id' => $rid]);
    echo "[repair] requeued record #{$rid}\n";
    $requeued++;
}

Log::info('repair done', [
    'requeued'               => $requeued,
    'refunded_no_origin'     => $refunded,
    'stale_processing_reset' => count($stuckProcessing),
]);
echo "[repair] done. requeued={$requeued}, refunded={$refunded}, processing_reset=" . count($stuckProcessing) . "\n";
