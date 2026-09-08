<?php
declare(strict_types=1);

namespace App\Jobs;

use App\Core\Log;
use App\Core\RedisClient;
use App\Services\AvatarGenerateService;

/**
 * 队列 Worker
 *
 * 启动方式（宝塔计划任务 / supervisor）：
 *   php ROOT_PATH/bin/worker.php avatar:generate
 *
 * 实现：BLPOP 阻塞消费 Redis 列表
 */
final class QueueWorker
{
    /** @var array<string,string> 队列名 → 处理器 */
    private static array $handlers = [
        AvatarGenerateService::QUEUE_NAME => [AvatarGenerateService::class, 'consume'],
    ];

    /**
     * 启动消费循环
     */
    public static function run(string $queue, int $timeout = 30, int $maxJobs = 100): void
    {
        if (!isset(self::$handlers[$queue])) {
            fwrite(STDERR, "Unknown queue: {$queue}\n");
            exit(1);
        }
        $handler = self::$handlers[$queue];
        $jobs = 0;
        Log::info('worker loop ready', ['queue' => $queue, 'pid' => getmypid()]);
        while ($jobs < $maxJobs) {
            try {
                $raw = RedisClient::pop($queue, $timeout);
            } catch (\Throwable $e) {
                // Redis 连接失败（重启/崩溃）：重置连接单例，3 秒后强制重连
                // 此时任务尚未被取出，不会丢失
                RedisClient::reset();
                Log::warning('worker queue unavailable, reconnect in 3s', ['queue' => $queue, 'err' => $e->getMessage()]);
                fwrite(STDERR, '[worker] queue unavailable: ' . $e->getMessage() . ", retry in 3s\n");
                sleep(3);
                continue;
            }
            if ($raw === null) {
                continue; // BLPOP 超时，无任务
            }
            $payload = json_decode($raw, true);
            if (!is_array($payload)) {
                Log::error('worker bad payload (json decode failed)', ['queue' => $queue, 'raw' => mb_substr((string)$raw, 0, 500)]);
                continue;
            }
            Log::info('worker job received', [
                'queue'     => $queue,
                'record_id' => $payload['record_id'] ?? null,
                'user_id'   => $payload['user_id'] ?? null,
            ]);
            $t0 = microtime(true);
            try {
                $handler($payload);
                Log::info('worker job done', [
                    'record_id' => $payload['record_id'] ?? null,
                    'cost_ms'   => (int)((microtime(true) - $t0) * 1000),
                ]);
            } catch (\Throwable $e) {
                Log::error('worker handler fatal', [
                    'record_id' => $payload['record_id'] ?? null,
                    'err'       => $e->getMessage(),
                    'file'      => $e->getFile() . ':' . $e->getLine(),
                ]);
                fwrite(STDERR, '[worker] ' . $e->getMessage() . "\n");
            }
            $jobs++;
        }
        Log::info('worker loop exit (max jobs reached)', ['queue' => $queue, 'jobs' => $jobs]);
    }
}
