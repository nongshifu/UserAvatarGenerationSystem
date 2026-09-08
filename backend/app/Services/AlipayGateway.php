<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Log;
use App\Models\Order;

/**
 * 支付宝电脑网站支付（alipay.trade.page.pay）
 * - 手写 RSA2 签名
 * - 表单/URL 跳转模式
 *
 * 依赖 openssl 扩展（PHP 自带）
 */
final class AlipayGateway
{
    private const GATEWAY = 'https://openapi.alipay.com/gateway.do';

    /**
     * 生成跳转 URL（GET 方式拼接 query）
     */
    public static function pagePay(Order $order, string $returnUrl): string
    {
        $appid = (string)Config::get('payment', 'alipay_appid', '');
        $privateKey = (string)Config::get('payment', 'alipay_private_key', '');
        $notifyUrl = (string)Config::get('payment', 'alipay_notify_url', '');
        $params = [
            'app_id'      => $appid,
            'method'      => 'alipay.trade.page.pay',
            'charset'     => 'utf-8',
            'sign_type'   => 'RSA2',
            'timestamp'   => date('Y-m-d H:i:s'),
            'version'     => '1.0',
            'notify_url'  => $notifyUrl,
            'return_url'  => $returnUrl,
            'biz_content' => json_encode([
                'out_trade_no' => (string)$order->order_no,
                'product_code' => 'FAST_INSTANT_TRADE_PAY',
                'total_amount' => (string)$order->amount,
                'subject'      => (string)$order->product_name,
            ], JSON_UNESCAPED_UNICODE),
        ];
        $params['sign'] = self::sign($params, $privateKey);
        return self::GATEWAY . '?' . http_build_query($params);
    }

    /**
     * 验证同步/异步返回
     */
    public static function verifyReturn(): ?array
    {
        $data = array_merge($_GET, $_POST);
        if (empty($data['out_trade_no'])) {
            return null;
        }
        $publicKey = (string)Config::get('payment', 'alipay_public_key', '');
        if ($publicKey === '' || !self::verify($data, $publicKey)) {
            Log::error('alipay notify sign fail', ['data' => $data]);
            return null;
        }
        if (($data['trade_status'] ?? '') !== 'TRADE_SUCCESS' && empty($data['trade_no'])) {
            return null;
        }
        return [
            'order_no'  => (string)$data['out_trade_no'],
            'trade_no'  => (string)($data['trade_no'] ?? ''),
        ];
    }

    /**
     * RSA2 签名
     */
    private static function sign(array $params, string $privateKeyPem): string
    {
        unset($params['sign'], $params['sign_type']);
        $params = array_filter($params, fn($v) => $v !== '' && $v !== null);
        ksort($params);
        $query = http_build_query($params);
        $key = self::normalizeKey($privateKeyPem, true);
        $signature = '';
        openssl_sign($query, $signature, $key, OPENSSL_ALGO_SHA256);
        return base64_encode($signature);
    }

    /**
     * RSA2 验签
     */
    private static function verify(array $data, string $publicKeyPem): bool
    {
        $sign = (string)($data['sign'] ?? '');
        unset($data['sign'], $data['sign_type']);
        $data = array_filter($data, fn($v) => $v !== '' && $v !== null);
        ksort($data);
        $query = http_build_query($data);
        $key = self::normalizeKey($publicKeyPem, false);
        $ok = openssl_verify($query, base64_decode($sign), $key, OPENSSL_ALGO_SHA256);
        return $ok === 1;
    }

    /**
     * 把配置字符串规范化为 PEM（用户常粘 1 行 base64 主体）
     */
    private static function normalizeKey(string $key, bool $isPrivate)
    {
        $key = trim($key);
        if (str_contains($key, '-----BEGIN')) {
            return openssl_pkey_get_private($key) ?: $key;
        }
        $wrapped = wordwrap($key, 64, "\n", true);
        $header = $isPrivate ? 'PRIVATE KEY' : 'PUBLIC KEY';
        $pem = "-----BEGIN {$header}-----\n{$wrapped}\n-----END {$header}-----\n";
        return $isPrivate ? openssl_pkey_get_private($pem) : openssl_pkey_get_public($pem);
    }
}
