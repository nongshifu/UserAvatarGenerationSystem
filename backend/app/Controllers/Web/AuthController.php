<?php
declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\SiteSettingService;
use App\Services\UserAuthService;

/**
 * 官网用户认证（注册/登录/退出）
 * - GET 渲染表单页（SSR）
 * - POST 处理表单，成功后下发 Cookie 并跳转控制台
 */
final class AuthController
{
    /** GET /login */
    public function loginForm(Request $req, Response $res, array $params): void
    {
        if (UserAuthService::currentUser()) {
            $res->redirect('/console');
            return;
        }
        View::display($res, 'auth/login', [
            'title'    => '登录 - 头像引擎',
            'siteName' => SiteSettingService::get('basic', 'site_name', '头像引擎'),
            'error'    => '',
            'username' => '',
        ]);
    }

    /** POST /login */
    public function login(Request $req, Response $res, array $params): void
    {
        $username = trim((string)$req->input('username', ''));
        $password = (string)$req->input('password', '');
        try {
            $result = UserAuthService::login($username, $password);
            UserAuthService::issueCookie($result['token']);
            $res->redirect('/console');
        } catch (\RuntimeException $e) {
            View::display($res, 'auth/login', [
                'title'    => '登录 - 头像引擎',
                'siteName' => SiteSettingService::get('basic', 'site_name', '头像引擎'),
                'error'    => $e->getMessage(),
                'username' => $username,
            ], 200);
        }
    }

    /** GET /register */
    public function registerForm(Request $req, Response $res, array $params): void
    {
        if (UserAuthService::currentUser()) {
            $res->redirect('/console');
            return;
        }
        View::display($res, 'auth/register', [
            'title'        => '注册 - 头像引擎',
            'siteName'     => SiteSettingService::get('basic', 'site_name', '头像引擎'),
            'registerBonus'=> (int)SiteSettingService::get('points', 'register_bonus', 100),
            'error'        => '',
            'username'     => '',
            'email'        => '',
        ]);
    }

    /** POST /register */
    public function register(Request $req, Response $res, array $params): void
    {
        $username = trim((string)$req->input('username', ''));
        $email    = trim((string)$req->input('email', ''));
        $password = (string)$req->input('password', '');
        $confirm  = (string)$req->input('password_confirm', '');
        if ($password !== $confirm) {
            $this->renderRegisterError($res, $username, $email, '两次输入的密码不一致');
            return;
        }
        try {
            $result = UserAuthService::register($username, $email, $password);
            UserAuthService::issueCookie($result['token']);
            $res->redirect('/console');
        } catch (\RuntimeException $e) {
            $this->renderRegisterError($res, $username, $email, $e->getMessage());
        }
    }

    /** GET /logout */
    public function logout(Request $req, Response $res, array $params): void
    {
        UserAuthService::clearCookie();
        $res->redirect('/');
    }

    /** GET /forgot 忘记密码页 */
    public function forgotForm(Request $req, Response $res, array $params): void
    {
        if (UserAuthService::currentUser()) {
            $res->redirect('/console');
            return;
        }
        View::display($res, 'auth/forgot', [
            'title'       => '找回密码 - 头像引擎',
            'siteName'    => SiteSettingService::get('basic', 'site_name', '头像引擎'),
            'smsEnabled'  => \App\Services\SmsService::enabled(),
            'mailEnabled' => \App\Services\MailService::enabled(),
        ]);
    }

    /** POST /forgot/code 发送找回密码验证码（AJAX JSON） */
    public function forgotCode(Request $req, Response $res, array $params): void
    {
        $channel = $req->input('channel') === 'sms' ? 'sms' : 'mail';
        $account = trim((string)$req->input('account', ''));

        if ($channel === 'sms') {
            if (!\App\Services\SmsService::validPhone($account)) {
                $res->json(['ok' => false, 'msg' => '手机号格式不正确']);
                return;
            }
            // 只能给已验证绑定的手机发找回验证码
            $user = \App\Core\DB::table('users')->where('phone', $account)->where('phone_verified', 1)->first();
            if (!$user) {
                $res->json(['ok' => false, 'msg' => '该手机号未绑定任何账号']);
                return;
            }
        } else {
            if (!filter_var($account, FILTER_VALIDATE_EMAIL)) {
                $res->json(['ok' => false, 'msg' => '邮箱格式不正确']);
                return;
            }
            $user = \App\Core\DB::table('users')->where('email', $account)->first();
            if (!$user) {
                $res->json(['ok' => false, 'msg' => '该邮箱未注册']);
                return;
            }
        }
        $r = \App\Services\VerifyCodeService::send($channel, $account, 'reset');
        $res->json(['ok' => $r['ok'], 'msg' => $r['msg']]);
    }

    /** POST /forgot/reset 校验验证码并重置密码（AJAX JSON） */
    public function forgotReset(Request $req, Response $res, array $params): void
    {
        $channel  = $req->input('channel') === 'sms' ? 'sms' : 'mail';
        $account  = trim((string)$req->input('account', ''));
        $code     = trim((string)$req->input('code', ''));
        $password = (string)$req->input('password', '');

        if (mb_strlen($password) < 6) {
            $res->json(['ok' => false, 'msg' => '新密码至少 6 位']);
            return;
        }
        if (!\App\Services\VerifyCodeService::verify($channel, $account, 'reset', $code)) {
            $res->json(['ok' => false, 'msg' => '验证码错误或已过期']);
            return;
        }
        // 验证码通过后再定位账号（防止枚举账号）
        if ($channel === 'sms') {
            $row = \App\Core\DB::table('users')->where('phone', $account)->where('phone_verified', 1)->first();
        } else {
            $row = \App\Core\DB::table('users')->where('email', $account)->first();
        }
        if (!$row) {
            $res->json(['ok' => false, 'msg' => '账号不存在']);
            return;
        }
        \App\Core\DB::raw('UPDATE users SET password = ? WHERE id = ?',
            [password_hash($password, PASSWORD_BCRYPT), (int)$row['id']]);
        $res->json(['ok' => true, 'msg' => '密码已重置，请使用新密码登录']);
    }

    private function renderRegisterError(Response $res, string $username, string $email, string $error): void
    {
        View::display($res, 'auth/register', [
            'title'        => '注册 - 头像引擎',
            'siteName'     => SiteSettingService::get('basic', 'site_name', '头像引擎'),
            'registerBonus'=> (int)SiteSettingService::get('points', 'register_bonus', 100),
            'error'        => $error,
            'username'     => $username,
            'email'        => $email,
        ], 200);
    }
}
