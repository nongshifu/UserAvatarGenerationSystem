<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\RedisClient;

/**
 * 验证码服务（短信 / 邮箱通用）
 *
 * 验证码存 Redis：
 *   vcode:{channel}:{scene}:{target}   验证码本身，TTL = 有效期
 *   vcode:cd:{channel}:{scene}:{target} 重发冷却标记，TTL = 冷却秒数
 *   vcode:cnt:{channel}:{target}:{Ymd}  当日发送计数，TTL 到次日凌晨
 *
 * channel：sms（短信）/ mail（邮箱）
 * scene：  bind（绑定/换绑手机）/ reset（找回密码）
 *
 * 降级模式：通道总开关已开但凭证未配置时，使用固定验证码 000000 并在消息中提示，
 * 方便站长先跑通业务再补凭证（与成熟项目做法一致）。
 */
final class VerifyCodeService
{
    public const EXPIRE   = 600;  // 验证码有效期 10 分钟
    public const COOLDOWN = 60;   // 重发冷却 60 秒
    public const DAILY_MAX = 10;  // 同一目标每日上限
    public const DEGRADED_CODE = '000000';

    /**
     * 发送验证码
     * @return array{ok:bool,msg:string}
     */
    public static function send(string $channel, string $target, string $scene, string $ip = ''): array
    {
        $channel = $channel === 'mail' ? 'mail' : 'sms';
        $scene   = in_array($scene, ['bind', 'reset'], true) ? $scene : 'bind';
        $target  = trim($target);

        // 目标格式与通道开关
        if ($channel === 'sms') {
            if (!SmsService::validPhone($target)) {
                return ['ok' => false, 'msg' => '手机号格式不正确'];
            }
            if (!SmsService::enabled()) {
                return ['ok' => false, 'msg' => '短信验证功能未开启，请联系站长'];
            }
        } else {
            if (!filter_var($target, FILTER_VALIDATE_EMAIL)) {
                return ['ok' => false, 'msg' => '邮箱格式不正确'];
            }
            if (!MailService::enabled()) {
                return ['ok' => false, 'msg' => '邮箱验证功能未开启，请联系站长'];
            }
        }

        // 降级模式：开关已开但凭证没配 → 固定验证码
        $degraded = ($channel === 'sms' && !SmsService::effectiveEnabled())
            || ($channel === 'mail' && !MailService::effectiveEnabled());

        // 重发冷却
        if (RedisClient::get(self::cdKey($channel, $scene, $target)) !== null) {
            return ['ok' => false, 'msg' => '发送太频繁，请 ' . self::COOLDOWN . ' 秒后再试'];
        }
        // 每日上限
        $cntKey = self::cntKey($channel, $target);
        $sentToday = (int)RedisClient::get($cntKey);
        if ($sentToday >= self::DAILY_MAX) {
            return ['ok' => false, 'msg' => '今日发送次数已达上限，请明天再试'];
        }

        $code = $degraded ? self::DEGRADED_CODE : str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // 先发送，成功后再落库（避免发送失败却进入冷却）
        if (!$degraded) {
            if ($channel === 'sms') {
                $r = SmsService::sendCode($target, $code);
            } else {
                $purpose = $scene === 'reset' ? '找回密码' : '验证邮箱';
                $r = MailService::sendVerifyCode($target, $code, $purpose);
            }
            if (!$r['ok']) {
                return ['ok' => false, 'msg' => $r['msg']];
            }
        }

        // 落库：验证码 + 冷却 + 日计数
        RedisClient::set(self::codeKey($channel, $scene, $target), $code, self::EXPIRE);
        RedisClient::set(self::cdKey($channel, $scene, $target), '1', self::COOLDOWN);
        if ($sentToday <= 0) {
            RedisClient::set($cntKey, '1', self::secondsToMidnight());
        } else {
            RedisClient::incr($cntKey);
        }

        if ($degraded) {
            $channelName = $channel === 'sms' ? '短信' : '邮箱';
            return ['ok' => true, 'msg' => "{$channelName}通道尚未配置凭证，当前为调试模式：验证码固定为 " . self::DEGRADED_CODE];
        }
        return ['ok' => true, 'msg' => '验证码已发送，' . intdiv(self::EXPIRE, 60) . ' 分钟内有效'];
    }

    /**
     * 校验验证码（一次性，通过即删除）
     */
    public static function verify(string $channel, string $target, string $scene, string $code): bool
    {
        $channel = $channel === 'mail' ? 'mail' : 'sms';
        $scene   = in_array($scene, ['bind', 'reset'], true) ? $scene : 'bind';
        $target  = trim($target);
        $code    = trim($code);
        if ($code === '' || !preg_match('/^\d{6}$/', $code)) {
            return false;
        }
        $stored = RedisClient::get(self::codeKey($channel, $scene, $target));
        if ($stored === null || !hash_equals((string)$stored, $code)) {
            return false;
        }
        RedisClient::del(self::codeKey($channel, $scene, $target));
        return true;
    }

    private static function codeKey(string $channel, string $scene, string $target): string
    {
        return "vcode:{$channel}:{$scene}:" . md5($target);
    }

    private static function cdKey(string $channel, string $scene, string $target): string
    {
        return "vcode:cd:{$channel}:{$scene}:" . md5($target);
    }

    private static function cntKey(string $channel, string $target): string
    {
        return 'vcode:cnt:' . $channel . ':' . date('Ymd') . ':' . md5($target);
    }

    /** 到当日 24:00 的剩余秒数 */
    private static function secondsToMidnight(): int
    {
        $now = time();
        return strtotime(date('Y-m-d 23:59:59')) - $now + 1;
    }
}
