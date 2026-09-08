<?php $active='profile'; include __DIR__ . '/../_partials/header.php'; ?>
<?php
$phoneMasked = '';
if (!empty($currentUser->phone)) {
    $p = (string)$currentUser->phone;
    $phoneMasked = strlen($p) >= 11 ? substr($p, 0, 3) . '****' . substr($p, -4) : $p;
}
// 邮箱脱敏：前两位 + **** + 域名
$emailMasked = '';
$_email = (string)($currentUser->email ?? '');
if (strpos($_email, '@') !== false) {
    [$_u, $_d] = explode('@', $_email, 2);
    $emailMasked = (mb_strlen($_u) > 2 ? mb_substr($_u, 0, 2) : $_u) . '****@' . $_d;
}
// 登录态验证码找回可用渠道
$canMailReset = !empty($mailEnabled);
$canSmsReset  = !empty($smsEnabled) && !empty($phoneVerified);
?>
<section class="container">
    <div class="console-layout">
        <?php include __DIR__ . '/../_partials/console_side.php'; ?>
        <div>
            <h2 style="margin-bottom:20px">个人资料</h2>

            <?php if (!empty($error)): ?>
            <div class="alert" style="background:rgba(245,108,108,.12);border-color:rgba(245,108,108,.4);color:#ff9c9c;margin-bottom:16px"><?= $e($error) ?></div>
            <?php endif; ?>
            <?php if (!empty($success)): ?>
            <div class="alert" style="background:rgba(103,194,58,.12);border-color:rgba(103,194,58,.4);color:#9be37c;margin-bottom:16px"><?= $e($success) ?></div>
            <?php endif; ?>
            <?php if ($forceVerify && !$phoneVerified): ?>
            <div class="alert" style="background:rgba(230,162,60,.12);border-color:rgba(230,162,60,.45);color:#ffd28a;margin-bottom:16px">
                站点已开启「强制手机验证」：绑定并验证手机号后才能生成头像。
            </div>
            <?php endif; ?>

            <!-- 基本资料 -->
            <div class="card" style="margin-bottom:16px">
                <h3 style="margin-bottom:14px">基本资料</h3>
                <form method="post" action="/console/profile/update">
                    <label>用户名（不可修改）</label>
                    <input type="text" value="<?= $e((string)$currentUser->username) ?>" disabled style="opacity:.6">
                    <label>邮箱（注册时填写）</label>
                    <input type="text" value="<?= $e((string)$currentUser->email) ?>" disabled style="opacity:.6">
                    <label>昵称</label>
                    <input type="text" name="nickname" value="<?= $e((string)($currentUser->nickname ?: $currentUser->username)) ?>" required maxlength="50">
                    <button type="submit" class="btn" style="margin-top:14px">保存资料</button>
                </form>
            </div>

            <!-- 修改密码 -->
            <div class="card" style="margin-bottom:16px">
                <h3 style="margin-bottom:14px">修改密码</h3>
                <form method="post" action="/console/profile/password">
                    <label>当前密码</label>
                    <input type="password" name="old_password" required placeholder="请输入现在使用的密码">
                    <label>新密码</label>
                    <input type="password" name="new_password" required minlength="6" placeholder="至少 6 位">
                    <label>确认新密码</label>
                    <input type="password" name="confirm_password" required minlength="6" placeholder="再次输入新密码">
                    <div class="row" style="align-items:center;margin-top:14px">
                        <button type="submit" class="btn" style="margin:0">更新密码</button>
                        <button type="button" class="btn btn-outline" id="toggle-reset" style="margin:0">忘记旧密码？用验证码找回</button>
                    </div>
                </form>

                <!-- 验证码找回面板（登录态，无需旧密码） -->
                <div id="reset-panel" hidden style="margin-top:18px;padding-top:16px;border-top:1px dashed rgba(255,255,255,.15)">
                    <p class="muted" style="margin-bottom:12px">不记得旧密码？选择当前账号已绑定的邮箱或手机，接收验证码后直接设置新密码：</p>
                    <div id="reset-msg"></div>
                    <?php if (!$canMailReset && !$canSmsReset): ?>
                    <p class="muted" style="color:#ff9c9c">邮箱/短信验证码找回均不可用（请站长在后台开启邮箱或短信配置，并先绑定手机）。</p>
                    <?php else: ?>
                    <div style="display:flex;gap:8px;margin-bottom:14px">
                        <?php if ($canMailReset): ?>
                        <button type="button" class="btn channel-btn active" data-rchannel="mail" style="flex:1">邮箱：<?= $e($emailMasked) ?></button>
                        <?php endif; ?>
                        <?php if ($canSmsReset): ?>
                        <button type="button" class="btn channel-btn<?= $canMailReset ? '' : ' active' ?>" data-rchannel="sms" style="flex:1">短信：<?= $e($phoneMasked) ?></button>
                        <?php endif; ?>
                    </div>
                    <label>验证码</label>
                    <div class="row" style="gap:10px;align-items:stretch">
                        <input type="text" id="reset-code" pattern="\d{6}" maxlength="6" placeholder="6 位验证码" style="flex:1;max-width:160px">
                        <button type="button" class="btn btn-outline" id="reset-send-btn" style="white-space:nowrap;margin:0">发送验证码</button>
                    </div>
                    <label style="margin-top:10px">新密码</label>
                    <input type="password" id="reset-pwd" minlength="6" placeholder="至少 6 位">
                    <label style="margin-top:10px">确认新密码</label>
                    <input type="password" id="reset-pwd2" minlength="6" placeholder="再次输入新密码">
                    <div class="row" style="margin-top:14px">
                        <button type="button" class="btn" id="reset-submit" style="margin:0">确认重置密码</button>
                        <button type="button" class="btn btn-outline" id="reset-cancel" style="margin:0">取消</button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 手机绑定 -->
            <div class="card" style="margin-bottom:16px">
                <h3 style="margin-bottom:6px">手机绑定</h3>
                <p class="muted" style="margin-bottom:14px">
                    <?php if ($phoneVerified): ?>
                        当前已绑定：<b style="color:#9be37c"><?= $e($phoneMasked) ?></b>（已验证）
                        如需更换手机号，在下方输入新号码并完成验证。
                    <?php else: ?>
                        绑定手机号后可用于短信找回密码；<?= $forceVerify ? '<b style="color:#ffd28a">当前站点要求验证手机后才能生成头像</b>' : '未绑定时不影响正常使用' ?>。
                    <?php endif; ?>
                </p>
                <?php if (!$smsEnabled): ?>
                <p class="muted" style="color:#ff9c9c">短信验证功能未开启，请联系站长在后台「短信配置」中启用。</p>
                <?php else: ?>
                <form method="post" action="/console/profile/phone-bind" id="phone-form">
                    <label><?= $phoneVerified ? '新手机号' : '手机号' ?></label>
                    <input type="tel" name="phone" id="phone-input" required pattern="1[3-9]\d{9}" placeholder="请输入 11 位手机号" value="" style="max-width:260px">
                    <label style="margin-top:10px">短信验证码</label>
                    <div class="row" style="gap:10px;align-items:stretch">
                        <input type="text" name="code" id="phone-code" required pattern="\d{6}" maxlength="6" placeholder="6 位验证码" style="flex:1;max-width:160px">
                        <button type="button" class="btn btn-outline" id="send-code-btn" style="white-space:nowrap">发送验证码</button>
                    </div>
                    <button type="submit" class="btn" style="margin-top:14px"><?= $phoneVerified ? '更换绑定手机' : '绑定手机' ?></button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php if ($smsEnabled): ?>
<script>
(function () {
    var btn = document.getElementById('send-code-btn');
    var phoneInput = document.getElementById('phone-input');
    if (!btn || !phoneInput) return;
    var timer = null;
    function setCountdown(sec) {
        btn.disabled = true;
        var left = sec;
        btn.textContent = left + ' 秒后重发';
        timer = setInterval(function () {
            left--;
            if (left <= 0) {
                clearInterval(timer);
                btn.disabled = false;
                btn.textContent = '发送验证码';
            } else {
                btn.textContent = left + ' 秒后重发';
            }
        }, 1000);
    }
    btn.addEventListener('click', function () {
        var phone = phoneInput.value.trim();
        if (!/^1[3-9]\d{9}$/.test(phone)) {
            alert('请输入正确的 11 位手机号');
            phoneInput.focus();
            return;
        }
        btn.disabled = true;
        btn.textContent = '发送中…';
        fetch('/console/profile/phone-code', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ phone: phone }),
        }).then(function (r) { return r.json(); }).then(function (data) {
            // 框架成功响应统一包装为 {code:0, data:{ok,msg}}，这里兼容解包
            var d = data && data.data ? data.data : data;
            alert(d.msg || (d.ok ? '验证码已发送' : '发送失败'));
            if (d.ok) {
                setCountdown(60);
            } else {
                btn.disabled = false;
                btn.textContent = '发送验证码';
            }
        }).catch(function () {
            alert('网络错误，请稍后重试');
            btn.disabled = false;
            btn.textContent = '发送验证码';
        });
    });
})();
</script>
<?php endif; ?>

<script>
(function () {
    var toggle = document.getElementById('toggle-reset');
    var panel = document.getElementById('reset-panel');
    if (!toggle || !panel) return;

    function setPanel(open) {
        panel.hidden = !open;
        toggle.textContent = open ? '收起验证码找回' : '忘记旧密码？用验证码找回';
    }
    toggle.addEventListener('click', function () { setPanel(panel.hidden); });
    var cancel = document.getElementById('reset-cancel');
    if (cancel) cancel.addEventListener('click', function () { setPanel(false); });

    // 发送验证码 / 提交按钮仅在至少一个渠道可用时存在
    var sendBtn = document.getElementById('reset-send-btn');
    var submitBtn = document.getElementById('reset-submit');
    if (!sendBtn || !submitBtn) return;

    // 渠道切换
    var channelBtns = panel.querySelectorAll('.channel-btn');
    var channel = '<?= $canMailReset ? 'mail' : 'sms' ?>';
    channelBtns.forEach(function (b) {
        b.addEventListener('click', function () {
            channel = b.dataset.rchannel;
            channelBtns.forEach(function (x) { x.classList.toggle('active', x === b); });
        });
    });

    var msgBox = document.getElementById('reset-msg');
    function showMsg(text, ok) {
        if (!text) { msgBox.innerHTML = ''; return; }
        var color = ok ? 'rgba(103,194,58,.12);border-color:rgba(103,194,58,.4);color:#9be37c'
                       : 'rgba(245,108,108,.12);border-color:rgba(245,108,108,.4);color:#ff9c9c';
        msgBox.innerHTML = '<div class="alert" style="background:' + color + ';margin:0 0 12px">' + String(text).replace(/</g, '&lt;') + '</div>';
    }
    // 统一 POST + 框架响应解包
    function post(url, payload) {
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        }).then(function (r) { return r.json(); })
          .then(function (data) { return data && data.data ? data.data : data; });
    }

    // 发送验证码
    var codeInput = document.getElementById('reset-code');
    var timer = null;
    function setCountdown(sec) {
        sendBtn.disabled = true;
        var left = sec;
        sendBtn.textContent = left + ' 秒后重发';
        timer = setInterval(function () {
            left--;
            if (left <= 0) {
                clearInterval(timer);
                sendBtn.disabled = false;
                sendBtn.textContent = '发送验证码';
            } else {
                sendBtn.textContent = left + ' 秒后重发';
            }
        }, 1000);
    }
    sendBtn.addEventListener('click', function () {
        sendBtn.disabled = true;
        sendBtn.textContent = '发送中…';
        post('/console/profile/reset-code', { channel: channel }).then(function (d) {
            showMsg(d.msg, d.ok);
            if (d.ok) {
                setCountdown(60);
                codeInput.focus();
            } else {
                sendBtn.disabled = false;
                sendBtn.textContent = '发送验证码';
            }
        }).catch(function () {
            showMsg('网络错误，请稍后重试', false);
            sendBtn.disabled = false;
            sendBtn.textContent = '发送验证码';
        });
    });

    // 确认重置
    var submitBtn = document.getElementById('reset-submit');
    var pwd1 = document.getElementById('reset-pwd');
    var pwd2 = document.getElementById('reset-pwd2');
    submitBtn.addEventListener('click', function () {
        var code = codeInput.value.trim();
        if (!/^\d{6}$/.test(code)) { showMsg('请输入 6 位验证码', false); return; }
        if (pwd1.value.length < 6) { showMsg('新密码至少 6 位', false); return; }
        if (pwd1.value !== pwd2.value) { showMsg('两次输入的新密码不一致', false); return; }
        submitBtn.disabled = true;
        submitBtn.textContent = '提交中…';
        post('/console/profile/reset-password', { channel: channel, code: code, password: pwd1.value })
            .then(function (d) {
                showMsg(d.msg, d.ok);
                submitBtn.disabled = false;
                submitBtn.textContent = '确认重置密码';
                if (d.ok) {
                    codeInput.value = '';
                    pwd1.value = '';
                    pwd2.value = '';
                }
            }).catch(function () {
                showMsg('网络错误，请稍后重试', false);
                submitBtn.disabled = false;
                submitBtn.textContent = '确认重置密码';
            });
    });
})();
</script>
<?php include __DIR__ . '/../_partials/footer.php'; ?>
