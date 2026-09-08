<?php
declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\SiteSettingService;
use App\Services\UserAuthService;
use App\Services\UserService;

/**
 * 充值
 * - GET  /console/recharge          套餐列表
 * - POST /console/recharge          下单（选套餐 + 支付方式）→ 跳支付
 * - GET  /console/recharge/return   支付返回页
 */
final class RechargeController
{
    /** GET /console/recharge */
    public function index(Request $req, Response $res, array $params): void
    {
        $user = UserAuthService::currentUser();
        if (!$user) {
            $res->redirect('/login');
            return;
        }
        View::display($res, 'console/recharge', [
            'title'       => '积分充值 - 头像引擎',
            'siteName'    => SiteSettingService::get('basic', 'site_name', '头像引擎'),
            'currentUser' => $user,
            'balance'     => UserService::balance((int)$user->id),
            'products'    => UserService::rechargeProducts(),
            'error'       => '',
        ]);
    }

    /** POST /console/recharge */
    public function create(Request $req, Response $res, array $params): void
    {
        $user = UserAuthService::currentUser();
        if (!$user) {
            $res->redirect('/login');
            return;
        }
        $productId = (int)$req->input('product_id', 0);
        $payMethod = (string)$req->input('pay_method', 'manual');
        try {
            $order = OrderService::create((int)$user->id, $productId, $payMethod);
            // 生成支付参数（扫码链接/二维码/跳转 URL）
            $pay = PaymentService::createCharge($order, $payMethod, 'console/recharge/return');
            if ($pay['type'] === 'redirect') {
                $res->redirect($pay['redirect_url']);
                return;
            }
            // qrcode / manual：展示二维码页
            View::display($res, 'console/recharge_pay', [
                'title'       => '请扫码支付 - 头像引擎',
                'siteName'    => SiteSettingService::get('basic', 'site_name', '头像引擎'),
                'currentUser' => $user,
                'order'        => $order->toArray(),
                'payQrcode'    => $pay['qrcode'] ?? '',
                'codeUrl'      => $pay['code_url'] ?? '',
                'payMethod'    => $payMethod,
            ]);
        } catch (\RuntimeException $e) {
            $this->renderError($res, $user, $e->getMessage());
        } catch (\Throwable $e) {
            $this->renderError($res, $user, '下单失败：' . $e->getMessage());
        }
    }

    /** GET /console/recharge/return */
    public function return(Request $req, Response $res, array $params): void
    {
        $user = UserAuthService::currentUser();
        if (!$user) {
            $res->redirect('/login');
            return;
        }
        View::display($res, 'console/recharge_return', [
            'title'       => '支付结果 - 头像引擎',
            'siteName'    => SiteSettingService::get('basic', 'site_name', '头像引擎'),
            'currentUser' => $user,
            'balance'     => UserService::balance((int)$user->id),
        ]);
    }

    private function renderError(Response $res, \App\Models\User $user, string $error): void
    {
        View::display($res, 'console/recharge', [
            'title'       => '积分充值 - 头像引擎',
            'siteName'    => SiteSettingService::get('basic', 'site_name', '头像引擎'),
            'currentUser' => $user,
            'balance'     => UserService::balance((int)$user->id),
            'products'    => UserService::rechargeProducts(),
            'error'       => $error,
        ]);
    }
}
