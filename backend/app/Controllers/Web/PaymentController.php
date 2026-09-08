<?php
declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Services\PaymentService;

/**
 * 支付回调（无登录态，由支付网关服务器调用）
 */
final class PaymentController
{
    /** POST /pay/notify/wechat */
    public function wechatNotify(Request $req, Response $res, array $params): void
    {
        $res->header('Content-Type', 'text/xml; charset=utf-8');
        $res->text(PaymentService::handleNotify('wechat'));
    }

    /** GET/POST /pay/notify/alipay */
    public function alipayNotify(Request $req, Response $res, array $params): void
    {
        $res->text(PaymentService::handleNotify('alipay'));
    }
}
