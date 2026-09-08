<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\User;

/**
 * 管理后台 JWT（极简实现，HS256，无第三方库）
 * Payload: {user_id, username, role, iat, exp}
 */
final class AdminAuthService
{
    private static function secret(): string
    {
        $s = getenv('APP_JWT_SECRET');
        if (!$s) {
            $s = 'change-me-in-production-please-' . __FILE__;
        }
        return $s;
    }

    /**
     * 签发 Token
     */
    public static function issue(User $user): string
    {
        $payload = [
            'user_id'  => (int)$user->id,
            'username' => $user->username,
            'role'     => $user->role,
            'iat'      => time(),
            'exp'      => time() + 7 * 86400,
        ];
        return self::encode($payload);
    }

    /**
     * 校验并返回 payload
     */
    public static function verify(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }
        [$h, $p, $s] = $parts;
        $expected = self::sign($h . '.' . $p);
        if (!hash_equals($expected, $s)) {
            return null;
        }
        $payload = json_decode(self::base64UrlDecode($p), true);
        if (!is_array($payload)) {
            return null;
        }
        if (($payload['exp'] ?? 0) < time()) {
            return null;
        }
        return $payload;
    }

    private static function encode(array $payload): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $h = self::base64UrlEncode(json_encode($header, JSON_UNESCAPED_UNICODE));
        $p = self::base64UrlEncode(json_encode($payload, JSON_UNESCAPED_UNICODE));
        $s = self::sign($h . '.' . $p);
        return $h . '.' . $p . '.' . $s;
    }

    private static function sign(string $input): string
    {
        return self::base64UrlEncode(hash_hmac('sha256', $input, self::secret(), true));
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string
    {
        $pad = strlen($data) % 4;
        if ($pad) {
            $data .= str_repeat('=', 4 - $pad);
        }
        return (string)base64_decode(strtr($data, '-_', '+/'));
    }
}
