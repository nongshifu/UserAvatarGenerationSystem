<?php
declare(strict_types=1);

namespace App\Services;

/**
 * 短信验证码服务（接口盒子 apihz.cn 代发）
 *
 * 配置位于后台「系统设置 → 短信配置」（site_settings 分组 sms）：
 *   sms_enabled        总开关
 *   sms_force_verify   业务强制：开启后未验证手机的账号无法生成头像
 *   apihz_id / apihz_key / apihz_url  接口盒子开发者凭证与接口地址
 *   sms_dynamic / sms_dmsg           动态秘钥模式（dcan + dkey）
 *
 * 设计参考成熟项目：开关开启但凭证未配置时进入降级模式（固定验证码 000000），
 * 方便先跑通业务；强制开关与短信通道是否可用相互独立，通道故障可临时关闭强制。
 */
final class SmsService
{
    /** 短信总开关 */
    public static function enabled(): bool
    {
        return self::bool('sms_enabled');
    }

    /** 通道是否真正可发（开关开 + 凭证已配） */
    public static function effectiveEnabled(): bool
    {
        return self::enabled()
            && self::get('apihz_id') !== ''
            && self::get('apihz_key') !== '';
    }

    /**
     * 业务强制手机验证：
     * 开启后，控制台/API 生成头像时要求账号已绑定并验证手机
     */
    public static function forceVerify(): bool
    {
        return self::bool('sms_force_verify');
    }

    /** 中国大陆手机号格式校验 */
    public static function validPhone(string $phone): bool
    {
        return (bool)preg_match('/^1[3-9]\d{9}$/', $phone);
    }

    /**
     * 发送验证码短信
     * @return array{ok:bool,msg:string}
     */
    public static function sendCode(string $phone, string $code): array
    {
        if (self::get('apihz_id') === '' || self::get('apihz_key') === '') {
            return ['ok' => false, 'msg' => '短信服务未配置（缺少接口盒子 ID/KEY），请联系站长在后台配置'];
        }

        $query = [
            'id'    => self::get('apihz_id'),
            'key'   => self::get('apihz_key'),
            'phone' => $phone,
            'code'  => $code,
        ];

        // 动态秘钥：先取一次性 dcan（10 秒有效），再 md5(dmsg + dcan) 得 dkey
        if (self::bool('sms_dynamic')) {
            $dcanUrl = 'https://cn.apihz.cn/api/xitong/dcan.php'
                . '?id=' . urlencode(self::get('apihz_id'))
                . '&key=' . urlencode(self::get('apihz_key'));
            $body = self::httpGet($dcanUrl, 5);
            if ($body === null) {
                return ['ok' => false, 'msg' => '动态秘钥获取失败（接口连接超时）'];
            }
            $json = json_decode($body, true);
            $dcan = null;
            if (is_array($json)) {
                $dcan = $json['data']['dcan'] ?? $json['dcan'] ?? (is_string($json['data'] ?? null) ? $json['data'] : null);
            } elseif (trim($body) !== '') {
                $dcan = trim($body);
            }
            if (!$dcan) {
                $msg = is_array($json) ? ($json['msg'] ?? '动态参数为空') : '动态参数为空';
                return ['ok' => false, 'msg' => '获取动态秘钥失败：' . $msg];
            }
            $query['dcan'] = $dcan;
            $query['dkey'] = md5(self::get('sms_dmsg') . $dcan);
        }

        $url = (self::get('apihz_url') ?: 'https://cn.apihz.cn/api/sms/dfapi.php')
            . '?' . http_build_query($query);
        $body = self::httpGet($url, 8);
        if ($body === null) {
            return ['ok' => false, 'msg' => '短信通道连接失败，请稍后重试'];
        }
        $json = json_decode($body, true);
        if (!is_array($json)) {
            return ['ok' => false, 'msg' => '短信通道响应异常，请稍后重试'];
        }
        if ((int)($json['code'] ?? 0) === 200) {
            return ['ok' => true, 'msg' => '发送成功'];
        }
        return ['ok' => false, 'msg' => '短信发送失败：' . ($json['msg'] ?? '未知错误')];
    }

    /** 简单 HTTP GET（优先 cURL，降级 file_get_contents） */
    private static function httpGet(string $url, int $timeout): ?string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => $timeout,
                CURLOPT_CONNECTTIMEOUT => max(2, $timeout - 3),
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
            ]);
            $body = curl_exec($ch);
            $err  = curl_errno($ch);
            curl_close($ch);
            return ($body === false || $err) ? null : (string)$body;
        }
        $ctx = stream_context_create([
            'http' => ['timeout' => $timeout],
            'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);
        $body = @file_get_contents($url, false, $ctx);
        return $body === false ? null : (string)$body;
    }

    private static function get(string $key): string
    {
        return trim((string)SiteSettingService::get('sms', $key, ''));
    }

    private static function bool(string $key): bool
    {
        $v = SiteSettingService::get('sms', $key, '0');
        return $v === '1' || $v === 1 || $v === 'true' || $v === true;
    }
}
