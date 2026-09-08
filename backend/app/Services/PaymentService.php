<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\DB;
use App\Core\Log;
use App\Models\Order;

/**
 * 支付网关抽象
 * - 手动（manual）：默认，创建待审订单，由管理员在后台核销
 * - 微信 Native（wechat）：返回 code_url，前端渲染二维码
 * - 支付宝（alipay）：返回跳转 URL
 *
 * 实际密钥/商户号通过 site_settings（payment 组）配置：
 *   wechat_appid, wechat_mchid, wechat_api_key, wechat_notify_url
 *   alipay_appid, alipay_private_key, alipay_public_key, alipay_notify_url
 */
final class PaymentService
{
    /**
     * 创建支付参数
     * @return array{type:string,redirect_url?:string,qrcode?:string,code_url?:string}
     */
    public static function createCharge(Order $order, string $method, string $returnUrl = ''): array
    {
        $method = strtolower($method);
        return match ($method) {
            'wechat'  => self::wechatCharge($order, $returnUrl),
            'alipay'  => self::alipayCharge($order, $returnUrl),
            default   => self::manualCharge($order),
        };
    }

    /**
     * 手动支付：返回空二维码，提示联系客服
     */
    private static function manualCharge(Order $order): array
    {
        return ['type' => 'qrcode', 'qrcode' => ''];
    }

    /**
     * 微信 Native 支付
     * - 配置完整时调 Native 下单返回 code_url
     * - 未配置时回退手动（订单仍为 pending，由后台核销）
     */
    private static function wechatCharge(Order $order, string $returnUrl): array
    {
        $appid = (string)Config::get('payment', 'wechat_appid', '');
        $mchid = (string)Config::get('payment', 'wechat_mchid', '');
        $apiKey = (string)Config::get('payment', 'wechat_api_key', '');
        if ($appid === '' || $mchid === '' || $apiKey === '') {
            return ['type' => 'qrcode', 'qrcode' => ''];
        }
        $notifyUrl = (string)Config::get('payment', 'wechat_notify_url', '');
        try {
            $codeUrl = WechatPayGateway::nativeOrder($order, $appid, $mchid, $apiKey, $notifyUrl);
            return ['type' => 'qrcode', 'code_url' => $codeUrl];
        } catch (\Throwable $e) {
            Log::error('wechat pay error', ['order' => $order->order_no, 'err' => $e->getMessage()]);
            return ['type' => 'qrcode', 'qrcode' => ''];
        }
    }

    /**
     * 支付宝电脑网站支付
     */
    private static function alipayCharge(Order $order, string $returnUrl): array
    {
        $appid = (string)Config::get('payment', 'alipay_appid', '');
        $privateKey = (string)Config::get('payment', 'alipay_private_key', '');
        if ($appid === '' || $privateKey === '') {
            return ['type' => 'qrcode', 'qrcode' => ''];
        }
        try {
            $url = AlipayGateway::pagePay($order, $returnUrl);
            return ['type' => 'redirect', 'redirect_url' => $url];
        } catch (\Throwable $e) {
            Log::error('alipay pay error', ['order' => $order->order_no, 'err' => $e->getMessage()]);
            return ['type' => 'qrcode', 'qrcode' => ''];
        }
    }

    /**
     * 支付回调路由
     * @return string 返回给网关的应答文本
     */
    public static function handleNotify(string $method): string
    {
        $method = strtolower($method);
        try {
            $result = match ($method) {
                'wechat' => WechatPayGateway::verifyNotify(),
                'alipay' => AlipayGateway::verifyReturn(),
                default  => null,
            };
            if (!$result || empty($result['order_no'])) {
                return $method === 'wechat' ? '<xml><return_code><![CDATA[FAIL]]></return_code></xml>' : 'fail';
            }
            $order = self::findOrderByNo($result['order_no']);
            if (!$order) {
                return 'fail';
            }
            // 幂等：仅 pending 才处理
            if ($order->status === 'pending') {
                $row = $order->toArray();
                OrderService::auditPaid((int)$row['id'], 0, '支付回调确认 ' . ($result['trade_no'] ?? ''));
                DB::table('orders')->where('id', (int)$row['id'])->update([
                    'pay_trade_no' => (string)($result['trade_no'] ?? ''),
                ]);
            }
            return $method === 'wechat' ? '<xml><return_code><![CDATA[SUCCESS]]></return_code></xml>' : 'success';
        } catch (\Throwable $e) {
            Log::error('payment notify error', ['method' => $method, 'err' => $e->getMessage()]);
            return 'fail';
        }
    }

    private static function findOrderByNo(string $orderNo): ?Order
    {
        $row = \App\Core\DB::table('orders')->where('order_no', $orderNo)->first();
        if (!$row) {
            return null;
        }
        return Order::find((int)$row['id']);
    }
}
