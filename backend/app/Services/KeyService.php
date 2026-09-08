<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Models\UserKey;

/**
 * 用户端 KEY 管理 Service
 * - 创建/列表/重置/启停
 * - api_key 明文仅创建/重置时返回一次，库中只存 hash
 */
final class KeyService
{
    private const PREFIX = 'ak_';

    /**
     * 列出用户的所有 KEY
     * @return array<int,array<string,mixed>>
     */
    public static function listByUser(int $userId): array
    {
        return DB::table('user_keys')
            ->select('id', 'name', 'callback_url', 'status', 'rate_limit', 'daily_limit', 'used_today', 'last_used_at', 'created_at')
            ->where('user_id', $userId)
            ->whereNull('deleted_at')
            ->orderBy('id', 'DESC')
            ->all();
    }

    /**
     * 创建 KEY，返回完整 api_key（仅此一次）
     */
    public static function create(int $userId, string $name, int $rateLimit = 0, int $dailyLimit = 0, string $callbackUrl = ''): array
    {
        $name = trim($name);
        if ($name === '') {
            throw new \RuntimeException('KEY 名称不能为空', 4001);
        }
        $apiKey = self::generateApiKey();
        $hash   = hash('sha256', $apiKey);
        // api_key 字段存脱敏展示用前缀，api_key_hash 用于查询
        $masked = substr($apiKey, 0, 8) . '****' . substr($apiKey, -4);
        UserKey::create([
            'user_id'      => $userId,
            'api_key'      => $masked,
            'api_key_hash' => $hash,
            'name'         => $name,
            'callback_url' => $callbackUrl,
            'status'       => 1,
            'rate_limit'   => $rateLimit,
            'daily_limit'  => $dailyLimit,
            'used_today'   => 0,
            'last_used_at' => null,
        ]);
        return ['id' => self::lastInsertId(), 'api_key' => $apiKey, 'name' => $name];
    }

    /**
     * 重置 KEY，返回新 api_key
     */
    public static function reset(int $userId, int $keyId): string
    {
        self::ownKey($userId, $keyId);
        $apiKey = self::generateApiKey();
        $hash   = hash('sha256', $apiKey);
        $masked = substr($apiKey, 0, 8) . '****' . substr($apiKey, -4);
        DB::table('user_keys')->where('id', $keyId)->update([
            'api_key'       => $masked,
            'api_key_hash'  => $hash,
            'used_today'    => 0,
        ]);
        return $apiKey;
    }

    /**
     * 启用/禁用
     */
    public static function toggle(int $userId, int $keyId): int
    {
        self::ownKey($userId, $keyId);
        $row = DB::table('user_keys')->select('status')->where('id', $keyId)->first();
        $newStatus = (int)$row['status'] === 1 ? 0 : 1;
        DB::table('user_keys')->where('id', $keyId)->update(['status' => $newStatus]);
        return $newStatus;
    }

    /**
     * 校验归属权
     */
    private static function ownKey(int $userId, int $keyId): array
    {
        $row = DB::table('user_keys')
            ->where('id', $keyId)
            ->where('user_id', $userId)
            ->whereNull('deleted_at')
            ->first();
        if (!$row) {
            throw new \RuntimeException('KEY 不存在或无权操作', 404);
        }
        return $row;
    }

    private static function generateApiKey(): string
    {
        $bytes = random_bytes(24);
        return self::PREFIX . bin2hex($bytes);
    }

    private static function lastInsertId(): int
    {
        return (int)DB::pdo()->lastInsertId();
    }
}
