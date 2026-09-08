<?php
declare(strict_types=1);

namespace App\Services;

/**
 * 邮件服务（SMTP，零依赖）
 *
 * 未引入 Composer/PHPMailer，内置极简 SMTP 客户端：
 * fsockopen 连接（465 走 ssl://，其余 tcp://）→ EHLO → AUTH LOGIN
 * → MAIL FROM → RCPT TO → DATA，正文 base64 编码避免中文乱码。
 *
 * 配置位于后台「系统设置 → 邮箱配置」（site_settings 分组 mail）：
 *   mail_enabled   总开关
 *   smtp_host      SMTP 服务器，如 smtp.qq.com
 *   smtp_port      端口，SSL 推荐 465
 *   smtp_user      发件邮箱
 *   smtp_pass      SMTP 授权码（不是登录密码！）
 *   from_name      发件人显示名
 */
final class MailService
{
    public static function enabled(): bool
    {
        return self::bool('mail_enabled');
    }

    /** 真正可发：开关开 + 邮箱和授权码已填 */
    public static function effectiveEnabled(): bool
    {
        return self::enabled()
            && self::get('smtp_user') !== ''
            && self::get('smtp_pass') !== '';
    }

    /**
     * 发送验证码邮件
     * @return array{ok:bool,msg:string}
     */
    public static function sendVerifyCode(string $to, string $code, string $purpose): array
    {
        $siteName = self::get('from_name') ?: (string)SiteSettingService::get('basic', 'site_name', '头像引擎');
        $subject = $siteName . ' - 邮箱验证码';
        $html = <<<EOT
<div style="max-width:520px;margin:0 auto;font-family:-apple-system,'PingFang SC','Microsoft YaHei',sans-serif;color:#333;">
  <div style="background:#f7f8fa;border-radius:12px;padding:28px;">
    <h2 style="margin:0 0 16px;font-size:20px;">{$purpose}</h2>
    <p style="margin:0 0 12px;line-height:1.7;">你好：</p>
    <p style="margin:0 0 16px;line-height:1.7;">你正在进行「{$purpose}」操作，验证码为：</p>
    <p style="margin:0 0 20px;text-align:center;">
      <span style="display:inline-block;font-size:30px;font-weight:700;letter-spacing:8px;color:#7b5cff;background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:10px 26px;">{$code}</span>
    </p>
    <p style="margin:0;line-height:1.7;color:#888;font-size:13px;">验证码 10 分钟内有效，请勿泄露给他人。如果这不是您的操作，请忽略本邮件。</p>
  </div>
</div>
EOT;
        return self::send($to, $subject, $html);
    }

    /**
     * SMTP 发信
     * @return array{ok:bool,msg:string}
     */
    public static function send(string $toEmail, string $subject, string $htmlBody): array
    {
        if (!self::effectiveEnabled()) {
            return ['ok' => false, 'msg' => '邮件功能未配置或未开启'];
        }
        $host = self::get('smtp_host') ?: 'smtp.qq.com';
        $port = (int)(self::get('smtp_port') ?: '465');
        $user = self::get('smtp_user');
        $pass = self::get('smtp_pass');
        $fromName = self::get('from_name') ?: (string)SiteSettingService::get('basic', 'site_name', '头像引擎');
        $transport = $port === 465 ? 'ssl://' : 'tcp://';

        $errno = 0;
        $errstr = '';
        $fp = @fsockopen($transport . $host, $port, $errno, $errstr, 15);
        if (!$fp) {
            return ['ok' => false, 'msg' => "无法连接邮件服务器 {$host}:{$port} - {$errstr}({$errno})"];
        }
        stream_set_timeout($fp, 15);

        // 读取 SMTP 多行响应：最后一行第 4 个字符是空格（如 "250 OK" vs "250-PIPELINING"）
        $read = function () use ($fp) {
            $data = '';
            while (($line = fgets($fp, 515)) !== false) {
                $data .= $line;
                if (strlen($line) < 4 || $line[3] === ' ') {
                    break;
                }
            }
            return $data;
        };
        $cmd = function (string $command, string $expect) use ($fp, $read) {
            fwrite($fp, $command . "\r\n");
            $resp = $read();
            if (substr($resp, 0, strlen($expect)) !== $expect) {
                return [false, "SMTP 响应异常：期望 {$expect}，实际 [{$resp}]"];
            }
            return [true, $resp];
        };

        $fail = '';
        do {
            $greeting = $read();
            if (substr($greeting, 0, 3) !== '220') {
                $fail = "SMTP 问候异常：[{$greeting}]";
                break;
            }
            $hostname = !empty($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';

            [$ok, $fail] = $cmd('EHLO ' . $hostname, '250');
            if (!$ok) {
                break;
            }
            [$ok, $fail] = $cmd('AUTH LOGIN', '334');
            if (!$ok) {
                break;
            }
            [$ok, $fail] = $cmd(base64_encode($user), '334');
            if (!$ok) {
                break;
            }
            [$ok, $fail] = $cmd(base64_encode($pass), '235');
            if (!$ok) {
                break;
            }
            [$ok, $fail] = $cmd('MAIL FROM:<' . $user . '>', '250');
            if (!$ok) {
                break;
            }
            [$ok, $fail] = $cmd('RCPT TO:<' . $toEmail . '>', '250');
            if (!$ok) {
                break;
            }
            [$ok, $fail] = $cmd('DATA', '354');
            if (!$ok) {
                break;
            }

            $headers = 'Date: ' . date('r') . "\r\n"
                . 'From: =?UTF-8?B?' . base64_encode($fromName) . "?= <{$user}>\r\n"
                . 'To: <' . $toEmail . ">\r\n"
                . 'Subject: =?UTF-8?B?' . base64_encode($subject) . "?=\r\n"
                . 'Message-ID: <' . md5(uniqid('', true)) . '@' . $hostname . ">\r\n"
                . "MIME-Version: 1.0\r\n"
                . "Content-Type: text/html; charset=UTF-8\r\n"
                . "Content-Transfer-Encoding: base64\r\n";
            $body = chunk_split(base64_encode($htmlBody));
            fwrite($fp, $headers . "\r\n" . $body . "\r\n.\r\n");
            $resp = $read();
            if (substr($resp, 0, 3) !== '250') {
                $fail = "邮件投递失败：[{$resp}]";
                break;
            }

            fwrite($fp, "QUIT\r\n");
            fclose($fp);
            return ['ok' => true, 'msg' => '发送成功'];
        } while (false);

        fclose($fp);
        return ['ok' => false, 'msg' => $fail];
    }

    private static function get(string $key): string
    {
        return trim((string)SiteSettingService::get('mail', $key, ''));
    }

    private static function bool(string $key): bool
    {
        $v = SiteSettingService::get('mail', $key, '0');
        return $v === '1' || $v === 1 || $v === 'true' || $v === true;
    }
}
