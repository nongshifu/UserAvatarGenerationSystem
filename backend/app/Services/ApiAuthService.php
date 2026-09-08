<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\UserKey;
use App\Models\User;

/**
 * 对外 API 鉴权（基于 X-API-Key）
 */
final class ApiAuthService
{
    /**
     * 校验 API Key，返回 KEY 模型 + 关联用户
     * @return array{key:UserKey,user:User}|null
     */
    public static function verify(string $apiKey): ?array
    {
        if (strlen($apiKey) < 16) {
            return null;
        }
        $key = UserKey::findByApiKey($apiKey);
        if (!$key || !$key->isActive()) {
            return null;
        }
        $user = User::find((int)$key->user_id);
        if (!$user || !$user->isActive()) {
            return null;
        }
        return ['key' => $key, 'user' => $user];
    }
}
