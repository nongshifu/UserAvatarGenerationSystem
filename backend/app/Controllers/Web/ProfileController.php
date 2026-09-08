<?php
declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\MailService;
use App\Services\SiteSettingService;
use App\Services\SmsService;
use App\Services\UserAuthService;
use App\Services\VerifyCodeService;

/**
 * 用户个人资料
 * - GET  /console/profile            资料页
 * - POST /console/profile/update      修改昵称
 * - POST /console/profile/password    修改密码（核对旧密码）
 * - POST /console/profile/phone-code  发送手机验证码（JSON）
 * - POST /console/profile/phone-bind  绑定/换绑手机（校验验证码）
 */
final class ProfileController
{
    private function userOrFail(Response $res): ?\App\Models\User
    {
        $user = UserAuthService::currentUser();
        if (!$user) {
            $res->redirect('/login');
            return null;
        }
        return $user;
    }

    /** GET /console/profile */
    public function index(Request $req, Response $res, array $params): void
    {
        $user = $this->userOrFail($res);
        if (!$user) {
            return;
        }
        $this->render($res, $user, '', '');
    }

    /** POST /console/profile/update */
    public function update(Request $req, Response $res, array $params): void
    {
        $user = $this->userOrFail($res);
        if (!$user) {
            return;
        }
        $nickname = trim((string)$req->input('nickname', ''));
        if ($nickname === '') {
            $this->render($res, $user, '昵称不能为空', '');
            return;
        }
        if (mb_strlen($nickname) > 50) {
            $this->render($res, $user, '昵称最长 50 个字符', '');
            return;
        }
        $user->setAttribute('nickname', $nickname);
        $user->save();
        $this->render($res, $user, '', '资料已保存');
    }

    /** POST /console/profile/password */
    public function password(Request $req, Response $res, array $params): void
    {
        $user = $this->userOrFail($res);
        if (!$user) {
            return;
        }
        $old = (string)$req->input('old_password', '');
        $new = (string)$req->input('new_password', '');
        $confirm = (string)$req->input('confirm_password', '');
        if (!$user->checkPassword($old)) {
            $this->render($res, $user, '旧密码不正确', '');
            return;
        }
        if (mb_strlen($new) < 6) {
            $this->render($res, $user, '新密码至少 6 位', '');
            return;
        }
        if ($new !== $confirm) {
            $this->render($res, $user, '两次输入的新密码不一致', '');
            return;
        }
        $user->setAttribute('password', password_hash($new, PASSWORD_BCRYPT));
        $user->save();
        $this->render($res, $user, '', '密码已修改');
    }

    /** POST /console/profile/phone-code （AJAX JSON） */
    public function phoneCode(Request $req, Response $res, array $params): void
    {
        $user = $this->userOrFail($res);
        if (!$user) {
            $res->json(['ok' => false, 'msg' => '请先登录']);
            return;
        }
        $phone = trim((string)$req->input('phone', ''));
        if (!SmsService::validPhone($phone)) {
            $res->json(['ok' => false, 'msg' => '手机号格式不正确']);
            return;
        }
        // 手机号是否已被其他账号验证绑定
        $taken = DB::table('users')
            ->where('phone', $phone)
            ->where('phone_verified', 1)
            ->first();
        if ($taken && (int)$taken['id'] !== (int)$user->id) {
            $res->json(['ok' => false, 'msg' => '该手机号已被其他账号绑定']);
            return;
        }
        $r = VerifyCodeService::send('sms', $phone, 'bind');
        $res->json(['ok' => $r['ok'], 'msg' => $r['msg']]);
    }

    /** POST /console/profile/phone-bind */
    public function phoneBind(Request $req, Response $res, array $params): void
    {
        $user = $this->userOrFail($res);
        if (!$user) {
            return;
        }
        $phone = trim((string)$req->input('phone', ''));
        $code = trim((string)$req->input('code', ''));
        if (!SmsService::validPhone($phone)) {
            $this->render($res, $user, '手机号格式不正确', '');
            return;
        }
        $taken = DB::table('users')
            ->where('phone', $phone)
            ->where('phone_verified', 1)
            ->first();
        if ($taken && (int)$taken['id'] !== (int)$user->id) {
            $this->render($res, $user, '该手机号已被其他账号绑定', '');
            return;
        }
        if (!VerifyCodeService::verify('sms', $phone, 'bind', $code)) {
            $this->render($res, $user, '验证码错误或已过期', '');
            return;
        }
        $user->setAttribute('phone', $phone);
        $user->setAttribute('phone_verified', 1);
        $user->save();
        // 刷新模型数据后渲染
        $fresh = \App\Models\User::find((int)$user->id);
        $this->render($res, $fresh ?? $user, '', '手机绑定成功');
    }

    /**
     * POST /console/profile/reset-code （AJAX JSON）
     * 登录态下忘记旧密码：向当前账号绑定的邮箱/手机发送重置验证码
     */
    public function resetCode(Request $req, Response $res, array $params): void
    {
        $user = $this->userOrFail($res);
        if (!$user) {
            return;
        }
        $channel = $req->input('channel') === 'sms' ? 'sms' : 'mail';
        $target = $this->resetTarget($user, $channel, $err);
        if ($target === null) {
            $res->json(['ok' => false, 'msg' => $err]);
            return;
        }
        $r = VerifyCodeService::send($channel, $target, 'reset');
        $res->json(['ok' => $r['ok'], 'msg' => $r['msg']]);
    }

    /**
     * POST /console/profile/reset-password （AJAX JSON）
     * 登录态下凭验证码直接设置新密码（无需旧密码）
     */
    public function resetPassword(Request $req, Response $res, array $params): void
    {
        $user = $this->userOrFail($res);
        if (!$user) {
            return;
        }
        $channel  = $req->input('channel') === 'sms' ? 'sms' : 'mail';
        $code     = trim((string)$req->input('code', ''));
        $password = (string)$req->input('password', '');

        $target = $this->resetTarget($user, $channel, $err);
        if ($target === null) {
            $res->json(['ok' => false, 'msg' => $err]);
            return;
        }
        if (mb_strlen($password) < 6) {
            $res->json(['ok' => false, 'msg' => '新密码至少 6 位']);
            return;
        }
        if (!VerifyCodeService::verify($channel, $target, 'reset', $code)) {
            $res->json(['ok' => false, 'msg' => '验证码错误或已过期']);
            return;
        }
        $user->setAttribute('password', password_hash($password, PASSWORD_BCRYPT));
        $user->save();
        $res->json(['ok' => true, 'msg' => '密码已重置成功，下次登录请使用新密码']);
    }

    /**
     * 解析重置密码的验证码目标：
     * 邮箱渠道 → 当前账号注册邮箱；短信渠道 → 当前账号已验证绑定的手机
     */
    private function resetTarget(\App\Models\User $user, string $channel, ?string &$err = null): ?string
    {
        if ($channel === 'sms') {
            if (!SmsService::enabled()) {
                $err = '短信验证功能未开启，请使用邮箱找回';
                return null;
            }
            $phone = trim((string)($user->phone ?? ''));
            if ($phone === '' || (int)($user->phone_verified ?? 0) !== 1) {
                $err = '当前账号尚未绑定手机，请使用邮箱找回';
                return null;
            }
            return $phone;
        }
        if (!MailService::enabled()) {
            $err = '邮箱验证功能未开启，请联系站长';
            return null;
        }
        $email = trim((string)($user->email ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $err = '当前账号未绑定有效邮箱';
            return null;
        }
        return $email;
    }

    private function render(Response $res, \App\Models\User $user, string $error, string $success): void
    {
        View::display($res, 'console/profile', [
            'title'         => '个人资料 - 头像引擎',
            'siteName'      => SiteSettingService::get('basic', 'site_name', '头像引擎'),
            'currentUser'   => $user,
            'balance'       => \App\Services\UserService::balance((int)$user->id),
            'error'         => $error,
            'success'       => $success,
            'smsEnabled'    => SmsService::enabled(),
            'mailEnabled'   => MailService::enabled(),
            'forceVerify'   => SmsService::forceVerify(),
            'phoneVerified' => (int)($user->phone_verified ?? 0) === 1,
        ]);
    }
}
