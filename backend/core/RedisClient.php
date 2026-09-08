<?php
declare(strict_types=1);

namespace App\Core;

use Redis;

/**
 * Redis 单例封装
 *
 * 用法：
 *   RedisClient::get('key');
 *   RedisClient::set('key', 'val', 60);
 *   RedisClient::push('queue', 'job');     // 入队
 *   RedisClient::pop('queue');             // 阻塞出队
 *
 * 故障策略：
 *   - 缓存类操作（get/set/del/hash/set 等）Redis 不可用时降级返回 null/false/空数组，
 *     不抛异常，保证页面可回源 DB 继续服务；
 *   - 队列操作（push/pop）故障时必须抛异常：入队失败要让请求显式报错（避免任务静默丢失），
 *     出队失败由 Worker 捕获后等待重试。
 */
final class RedisClient
{
    private static ?Redis $client = null;

    /**
     * 获取连接（失败抛异常）
     */
    public static function client(): Redis
    {
        if (self::$client instanceof Redis) {
            return self::$client;
        }
        $cfg = self::config();
        try {
            $redis = new Redis();
            $connected = $redis->connect($cfg['host'], (int)$cfg['port'], (float)$cfg['timeout']);
            if (!$connected) {
                throw new \RuntimeException('connect returned false: ' . ($redis->getLastError() ?: 'unknown'));
            }
            if (!empty($cfg['password'])) {
                $redis->auth($cfg['password']);
            }
            if (!empty($cfg['database'])) {
                $redis->select((int)$cfg['database']);
            }
            $redis->setOption(Redis::OPT_PREFIX, $cfg['prefix'] ?? '');
            self::$client = $redis;
            return $redis;
        } catch (\Throwable $e) {
            self::$client = null;
            throw new \RuntimeException('Redis connect failed: ' . $e->getMessage(), 500, $e);
        }
    }

    /**
     * 重置连接单例（强制下次重连）
     * Worker 在 BLPOP 等队列操作捕获连接异常后调用，避免死连接被反复复用
     */
    public static function reset(): void
    {
        self::$client = null;
    }

    /**
     * 缓存类操作的故障降级包装：
     * Redis 连接/操作失败时记录日志并返回 $fallback，同时清空单例以便下次重连。
     */
    private static function cache(string $op, callable $fn, mixed $fallback): mixed
    {
        try {
            return $fn(self::client());
        } catch (\Throwable $e) {
            self::$client = null;
            Log::warning('redis unavailable (cache degraded)', ['op' => $op, 'err' => $e->getMessage()]);
            return $fallback;
        }
    }

    public static function get(string $key): mixed
    {
        return self::cache('get:' . $key, function (Redis $r) use ($key) {
            $val = $r->get($key);
            return $val === false ? null : $val;
        }, null);
    }

    public static function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        return self::cache('set:' . $key, function (Redis $r) use ($key, $value, $ttl) {
            $v = is_scalar($value) ? (string)$value : json_encode($value);
            return $ttl !== null && $ttl > 0 ? (bool)$r->setex($key, $ttl, $v) : (bool)$r->set($key, $v);
        }, false);
    }

    public static function del(string $key): int
    {
        return self::cache('del:' . $key, fn(Redis $r) => (int)$r->del($key), 0);
    }

    public static function has(string $key): bool
    {
        return self::cache('has:' . $key, fn(Redis $r) => (bool)$r->exists($key), false);
    }

    public static function incr(string $key, int $by = 1): int
    {
        return self::cache('incr:' . $key, fn(Redis $r) => (int)$r->incrBy($key, $by), 0);
    }

    public static function expire(string $key, int $ttl): bool
    {
        return self::cache('expire:' . $key, fn(Redis $r) => (bool)$r->expire($key, $ttl), false);
    }

    // ── 队列（故障必须抛出，由调用方/Worker 处理） ───────────────

    public static function push(string $queue, mixed $job): int
    {
        return self::client()->lPush($queue, is_scalar($job) ? (string)$job : json_encode($job));
    }

    /** 阻塞出队，timeout 秒（Redis 故障时抛异常） */
    public static function pop(string $queue, int $timeout = 0): ?string
    {
        $result = self::client()->blPop([$queue], $timeout);
        if ($result === false || $result === null) {
            return null; // 超时无任务
        }
        // phpredis blPop 返回一维数组 ['队列名', '元素']，元素在 [1]
        return $result[1] ?? null;
    }

    // ── Set / Hash（缓存用途，故障降级） ─────────────────────────

    public static function sAdd(string $key, mixed ...$members): int
    {
        return self::cache('sAdd:' . $key, function (Redis $r) use ($key, $members) {
            $args = array_map(fn($m) => is_scalar($m) ? (string)$m : json_encode($m), $members);
            return (int)$r->sAdd($key, ...$args);
        }, 0);
    }

    public static function sMembers(string $key): array
    {
        return self::cache('sMembers:' . $key, function (Redis $r) use ($key) {
            $arr = $r->sMembers($key);
            return is_array($arr) ? $arr : [];
        }, []);
    }

    public static function sRandMember(string $key, int $count = 1): array
    {
        return self::cache('sRandMember:' . $key, function (Redis $r) use ($key, $count) {
            $result = $r->sRandMember($key, $count);
            return is_array($result) ? $result : [];
        }, []);
    }

    public static function hSet(string $key, string $field, mixed $value): int
    {
        return self::cache('hSet:' . $key, fn(Redis $r) => (int)$r->hSet($key, $field, is_scalar($value) ? (string)$value : json_encode($value)), 0);
    }

    public static function hGet(string $key, string $field): mixed
    {
        return self::cache('hGet:' . $key, function (Redis $r) use ($key, $field) {
            $val = $r->hGet($key, $field);
            return $val === false ? null : $val;
        }, null);
    }

    public static function hGetAll(string $key): array
    {
        return self::cache('hGetAll:' . $key, function (Redis $r) use ($key) {
            $arr = $r->hGetAll($key);
            return is_array($arr) ? $arr : [];
        }, []);
    }

    private static function config(): array
    {
        $file = ROOT_PATH . '/config/redis.php';
        $cfg = is_file($file) ? require $file : [];
        return [
            'host'     => getenv('REDIS_HOST') ?: ($cfg['host']     ?? '127.0.0.1'),
            'port'     => getenv('REDIS_PORT') ?: ($cfg['port']     ?? 6379),
            'password' => getenv('REDIS_PASSWORD') ?: ($cfg['password'] ?? ''),
            'database' => getenv('REDIS_DATABASE') ?: ($cfg['database'] ?? 0),
            'prefix'   => $cfg['prefix'] ?? 'avatar_',
            'timeout'  => 2.0,
        ];
    }
}
