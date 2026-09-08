<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\RedisClient;
use App\Models\UserKey;

/**
 * 限流服务
 * - QPS：基于 Redis 固定窗口（秒级），超限返回 false
 * - 日限额：基于 user_keys.used_today 字段，每日清零
 *
 * 实现说明：
 *   QPS 用 key:rate:{keyId}:{second} 计数器，TTL 1s
 *   日限额用 key:daily:{keyId}:{date} 计数器，TTL 到当日结束
 */
final class RateLimitService
{
    /**
     * 检查是否允许调用（同时检查 QPS + 日限额）
     * @return array{ok:bool,reason:string,http_status?:int}
     */
    public static function check(UserKey $key): array
    {
        $keyId = (int)$key->id;
        $qps = (int)$key->rate_limit;
        $daily = (int)$key->daily_limit;

        // 日限额
        if ($daily > 0) {
            $today = date('Y-m-d');
            $dailyKey = "rate:daily:{$keyId}:{$today}";
            $used = (int)RedisClient::get($dailyKey);
            if ($used >= $daily) {
                return ['ok' => false, 'reason' => 'Daily limit exceeded', 'http_status' => 429];
            }
        }

        // QPS
        if ($qps > 0) {
            $sec = date('YmdHis');
            $qpsKey = "rate:qps:{$keyId}:{$sec}";
            $count = RedisClient::incr($qpsKey);
            if ($count === 1) {
                RedisClient::expire($qpsKey, 1);
            }
            if ($count > $qps) {
                return ['ok' => false, 'reason' => 'Rate limit exceeded', 'http_status' => 429];
            }
        }

        return ['ok' => true, 'reason' => ''];
    }

    /**
     * 记录一次调用（计入日限额 + 同步 used_today 字段）
     */
    public static function consume(UserKey $key): void
    {
        $keyId = (int)$key->id;
        $today = date('Y-m-d');
        $dailyKey = "rate:daily:{$keyId}:{$today}";
        $used = RedisClient::incr($dailyKey);
        if ($used === 1) {
            // 到当日结束的 TTL
            $ttl = (int)(strtotime('tomorrow') - time());
            RedisClient::expire($dailyKey, max(1, $ttl));
        }
        // 同步 DB（防止缓存丢失）
        $key->markUsed();
    }

    /**
     * IP 限流（首页测试生成等用）
     */
    public static function checkIp(string $ip, string $scene, int $max, int $windowSec): bool
    {
        $key = "rate:ip:{$scene}:{$ip}";
        $count = RedisClient::incr($key);
        if ($count === 1) {
            RedisClient::expire($key, $windowSec);
        }
        return $count <= $max;
    }
}
