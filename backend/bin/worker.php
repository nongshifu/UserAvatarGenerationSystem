<?php
/**
 * 队列 Worker 启动脚本
 *
 * 用法：
 *   php backend/bin/worker.php avatar:generate
 *   php backend/bin/worker.php avatar:generate --timeout=30 --max=1000
 *
 * 推荐：用 supervisor 或宝塔「进程管理」守护该进程
 */
declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__)); // backend/

spl_autoload_register(function (string $class): void {
    // App\Core\* → core/，其余 App\* → app/
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

$queue   = $argv[1] ?? null;
$timeout = 30;
$max     = 1000;

foreach (array_slice($argv, 2) as $arg) {
    if (preg_match('/^--timeout=(\d+)$/', $arg, $m)) {
        $timeout = (int)$m[1];
    } elseif (preg_match('/^--max=(\d+)$/', $arg, $m)) {
        $max = (int)$m[1];
    }
}

if (!$queue) {
    fwrite(STDERR, "Usage: php worker.php <queue> [--timeout=30] [--max=1000]\n");
    fwrite(STDERR, "Available queues: avatar:generate\n");
    exit(1);
}

echo "[worker] start queue={$queue} timeout={$timeout} max={$max}\n";

// ── 启动自检：把 Redis/DB 连通性与队列积压写入日志文件，便于排查「一直排队中」 ──
try {
    $redis = \App\Core\RedisClient::client();
    $len   = (int)$redis->lLen($queue);
    \App\Core\Log::info('worker boot redis ok', [
        'queue'       => $queue,
        'pid'         => getmypid(),
        'pending_len' => $len, // 启动时队列积压任务数
    ]);
} catch (\Throwable $e) {
    \App\Core\Log::error('worker boot redis FAILED', ['queue' => $queue, 'err' => $e->getMessage()]);
    fwrite(STDERR, "[worker] Redis 连接失败：" . $e->getMessage() . "\n");
}
try {
    $pending = \App\Core\DB::table('generation_records')->where('status', 'pending')->count();
    $processing = \App\Core\DB::table('generation_records')->where('status', 'processing')->count();
    \App\Core\Log::info('worker boot db ok', [
        'db_pending'    => $pending,    // DB 中排队中的记录数
        'db_processing' => $processing, // DB 中处理中的记录数（Worker 异常退出可能残留）
    ]);
} catch (\Throwable $e) {
    \App\Core\Log::error('worker boot db FAILED', ['err' => $e->getMessage()]);
    fwrite(STDERR, "[worker] DB 连接失败：" . $e->getMessage() . "\n");
}

\App\Jobs\QueueWorker::run($queue, $timeout, $max);
echo "[worker] exit\n";
