<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Log;
use App\Models\Order;

/**
 * 微信支付 Native（扫码）
 * - 手写签名（MD5），无 SDK
 * - 下单 → code_url；回调 → 验签 → 返回 order_no + trade_no
 *
 * 注意：v3 API 用 RSA 签名较复杂，这里实现 v2 统一下单（mch 商户号 + API 密钥）
 */
final class WechatPayGateway
{
    private const ORDER_URL = 'https://api.mch.weixin.qq.com/pay/unifiedorder';

    /**
     * 统一下单，返回 code_url
     */
    public static function nativeOrder(Order $order, string $appid, string $mchid, string $apiKey, string $notifyUrl): string
    {
        $params = [
            'appid'            => $appid,
            'mch_id'           => $mchid,
            'nonce_str'        => self::nonce(),
            'body'             => (string)$order->product_name,
            'out_trade_no'     => (string)$order->order_no,
            'total_fee'        => (int)round((float)$order->amount * 100),
            'spbill_create_ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            'notify_url'       => $notifyUrl,
            'trade_type'       => 'NATIVE',
            'product_id'       => (string)$order->product_id,
        ];
        $params['sign'] = self::sign($params, $apiKey);
        $xml = self::toXml($params);

        $ch = curl_init(self::ORDER_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS     => $xml,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_HTTPHEADER     => ['Content-Type: text/xml; charset=utf-8'],
        ]);
        $resp = (string)curl_exec($ch);
        curl_close($ch);
        $data = self::fromXml($resp);
        if (($data['return_code'] ?? '') !== 'SUCCESS' || ($data['result_code'] ?? '') !== 'SUCCESS') {
            Log::error('wechat order fail', ['resp' => $data]);
            throw new \RuntimeException('Wechat order failed: ' . ($data['return_msg'] ?? $data['err_code_des'] ?? 'unknown'));
        }
        return (string)$data['code_url'];
    }

    /**
     * 处理异步通知，返回 [order_no, trade_no] 或 null
     */
    public static function verifyNotify(): ?array
    {
        $raw = file_get_contents('php://input');
        if ($raw === '' || $raw === false) {
            return null;
        }
        $data = self::fromXml((string)$raw);
        $apiKey = (string)\App\Core\Config::get('payment', 'wechat_api_key', '');
        $sign = $data['sign'] ?? '';
        unset($data['sign']);
        if (!hash_equals(self::sign($data, $apiKey), $sign)) {
            return null;
        }
        if (($data['result_code'] ?? '') !== 'SUCCESS') {
            return null;
        }
        return [
            'order_no'  => (string)($data['out_trade_no'] ?? ''),
            'trade_no'  => (string)($data['transaction_id'] ?? ''),
        ];
    }

    /**
     * 微信 MD5 签名
     */
    private static function sign(array $params, string $apiKey): string
    {
        unset($params['sign']);
        $params = array_filter($params, fn($v) => $v !== '' && $v !== null);
        ksort($params);
        $parts = [];
        foreach ($params as $k => $v) {
            $parts[] = $k . '=' . $v;
        }
        $parts[] = 'key=' . $apiKey;
        return strtoupper(md5(implode('&', $parts)));
    }

    private static function nonce(): string
    {
        return bin2hex(random_bytes(8));
    }

    private static function toXml(array $data): string
    {
        $xml = '<xml>';
        foreach ($data as $k => $v) {
            $xml .= '<' . $k . '><![CDATA[' . $v . ']]></' . $k . '>';
        }
        return $xml . '</xml>';
    }

    private static function fromXml(string $xml): array
    {
        $obj = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        if (!$obj) {
            return [];
        }
        return json_decode(json_encode($obj), true) ?: [];
    }
}
