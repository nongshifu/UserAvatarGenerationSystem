<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Models\PointLog;
use App\Models\User;

/**
 * 用户端认证（注册/登录/JWT）
 * 与 AdminAuthService 共享 JWT 编码逻辑，但签发 7 天有效期 + role=user/developer
 */
final class UserAuthService
{
    /**
     * 注册
     * @return array{user:User,token:string}
     */
    public static function register(string $username, string $email, string $password): array
    {
        $username = trim($username);
        if (mb_strlen($username) < 3 || mb_strlen($username) > 50) {
            throw new \RuntimeException('用户名长度需 3-50', 4001);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('邮箱格式错误', 4001);
        }
        if (mb_strlen($password) < 6) {
            throw new \RuntimeException('密码至少 6 位', 4001);
        }
        if (DB::table('users')->where('username', $username)->first()) {
            throw new \RuntimeException('用户名已存在', 4001);
        }
        if (DB::table('users')->where('email', $email)->first()) {
            throw new \RuntimeException('邮箱已注册', 4001);
        }

        return DB::transaction(function () use ($username, $email, $password) {
            $user = User::create([
                'username'  => $username,
                'email'     => $email,
                'password'  => password_hash($password, PASSWORD_BCRYPT),
                'nickname'  => $username,
                'role'      => 'user',
                'status'    => 1,
                'points'    => 0,
            ]);
            // 注册赠送积分
            $bonus = (int)SiteSettingService::get('points', 'register_bonus', 100);
            if ($bonus > 0) {
                // 行锁
                DB::raw('SELECT points FROM users WHERE id = ? FOR UPDATE', [(int)$user->id])->fetch();
                $newBal = $bonus; // 注册前为 0
                DB::raw('UPDATE users SET points = ?, total_recharge = total_recharge + ? WHERE id = ?',
                    [$newBal, $bonus, (int)$user->id]);
                PointLog::record((int)$user->id, 'register', $bonus, $newBal, 0, '注册赠送');
            }
            $token = self::issueToken($user);
            // 重新取出最新积分
            $fresh = User::find((int)$user->id);
            return ['user' => $fresh ?? $user, 'token' => $token];
        });
    }

    /**
     * 登录
     * @return array{user:User,token:string}
     */
    public static function login(string $username, string $password): array
    {
        $row = DB::table('users')
            ->where('username', $username)
            ->orWhere('email', $username)
            ->first();
        if (!$row) {
            throw new \RuntimeException('用户不存在', 4002);
        }
        $user = User::find((int)$row['id']);
        if (!$user || !$user->checkPassword($password)) {
            throw new \RuntimeException('密码错误', 4002);
        }
        if (!$user->isActive()) {
            throw new \RuntimeException('账号已被禁用', 4003);
        }
        return ['user' => $user, 'token' => self::issueToken($user)];
    }

    public static function issueToken(User $user): string
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

    public static function verify(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }
        [$h, $p, $s] = $parts;
        if (!hash_equals(self::sign($h . '.' . $p), $s)) {
            return null;
        }
        $payload = json_decode(self::b64UrlDecode($p), true);
        if (!is_array($payload) || ($payload['exp'] ?? 0) < time()) {
            return null;
        }
        return $payload;
    }

    public const COOKIE_NAME = 'avatar_token';

    /** 下发 HttpOnly Cookie（7 天） */
    public static function issueCookie(string $token, int $ttl = 604800): void
    {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        setcookie(self::COOKIE_NAME, $token, [
            'expires'  => time() + $ttl,
            'path'     => '/',
            'httponly' => true,
            'secure'   => $isHttps,
            'samesite' => 'Lax',
        ]);
        $_COOKIE[self::COOKIE_NAME] = $token;
    }

    /** 清除 Cookie */
    public static function clearCookie(): void
    {
        setcookie(self::COOKIE_NAME, '', [
            'expires'  => 1,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        unset($_COOKIE[self::COOKIE_NAME]);
    }

    /** 从 Cookie 读取并校验，返回 User 或 null */
    public static function currentUser(): ?User
    {
        $token = $_COOKIE[self::COOKIE_NAME] ?? '';
        if ($token === '') {
            return null;
        }
        $payload = self::verify($token);
        if (!$payload) {
            return null;
        }
        $user = User::find((int)($payload['user_id'] ?? 0));
        return ($user && $user->isActive()) ? $user : null;
    }

    private static function encode(array $payload): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $h = self::b64UrlEncode(json_encode($header, JSON_UNESCAPED_UNICODE));
        $p = self::b64UrlEncode(json_encode($payload, JSON_UNESCAPED_UNICODE));
        return $h . '.' . $p . '.' . self::sign($h . '.' . $p);
    }

    private static function sign(string $input): string
    {
        return self::b64UrlEncode(hash_hmac('sha256', $input, self::secret(), true));
    }

    private static function secret(): string
    {
        return getenv('APP_JWT_SECRET') ?: 'avatar-user-jwt-secret-' . __FILE__;
    }

    private static function b64UrlEncode(string $d): string
    {
        return rtrim(strtr(base64_encode($d), '+/', '-_'), '=');
    }

    private static function b64UrlDecode(string $d): string
    {
        $pad = strlen($d) % 4;
        if ($pad) {
            $d .= str_repeat('=', 4 - $pad);
        }
        return (string)base64_decode(strtr($d, '-_', '+/'));
    }
}
