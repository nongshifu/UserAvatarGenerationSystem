<?php include __DIR__ . '/../_partials/header.php'; ?>
<section class="container section">
    <div class="form-card card" style="max-width:460px;margin:0 auto">
        <h2 style="margin-bottom:8px;text-align:center">找回密码</h2>
        <p class="muted" style="text-align:center;margin-bottom:18px">通过邮箱或手机验证码重置密码</p>

        <div id="msg"></div>

        <?php if (!$mailEnabled && !$smsEnabled): ?>
        <div class="alert" style="background:rgba(245,108,108,.12);border-color:rgba(245,108,108,.4);color:#ff9c9c">
            站点尚未启用邮箱/短信验证，请联系站长处理。
        </div>
        <?php else: ?>
        <div style="display:flex;gap:8px;margin-bottom:16px">
            <?php if ($mailEnabled): ?>
            <button type="button" class="btn channel-btn active" data-channel="mail" style="flex:1">邮箱验证码</button>
            <?php endif; ?>
            <?php if ($smsEnabled): ?>
            <button type="button" class="btn channel-btn<?= $mailEnabled ? '' : ' active' ?>" data-channel="sms" style="flex:1">短信验证码</button>
            <?php endif; ?>
        </div>

        <div id="step-form">
            <label id="account-label">注册邮箱</label>
            <input type="text" id="account" required autofocus placeholder="">
            <label style="margin-top:12px">验证码</label>
            <div class="row" style="gap:10px;align-items:stretch">
                <input type="text" id="code" required pattern="\d{6}" maxlength="6" placeholder="6 位验证码" style="flex:1">
                <button type="button" class="btn btn-outline" id="send-code-btn" style="white-space:nowrap">发送验证码</button>
            </div>
            <label style="margin-top:12px">新密码</label>
            <input type="password" id="password" required minlength="6" placeholder="至少 6 位">
            <button type="button" class="btn" id="reset-btn" style="width:100%;margin-top:18px">重置密码</button>
        </div>
        <?php endif; ?>

        <p class="muted" style="text-align:center;margin-top:14px">
            想起密码了？<a href="/login" style="color:#7b5cff">返回登录</a>
        </p>
    </div>
</section>

<script>
(function () {
    var channelBtns = document.querySelectorAll('.channel-btn');
    var accountInput = document.getElementById('account');
    var accountLabel = document.getElementById('account-label');
    var sendBtn = document.getElementById('send-code-btn');
    var resetBtn = document.getElementById('reset-btn');
    var codeInput = document.getElementById('code');
    var pwdInput = document.getElementById('password');
    var msgBox = document.getElementById('msg');
    var channel = '<?= $mailEnabled ? 'mail' : 'sms' ?>';

    function showMsg(text, ok) {
        if (!text) { msgBox.innerHTML = ''; return; }
        var color = ok ? 'rgba(103,194,58,.12);border-color:rgba(103,194,58,.4);color:#9be37c'
                       : 'rgba(245,108,108,.12);border-color:rgba(245,108,108,.4);color:#ff9c9c';
        msgBox.innerHTML = '<div class="alert" style="background:' + color + ';margin-bottom:14px">' + text.replace(/</g, '&lt;') + '</div>';
    }
    function applyChannel() {
        channelBtns.forEach(function (b) {
            b.classList.toggle('active', b.dataset.channel === channel);
        });
        var isSms = channel === 'sms';
        accountLabel.textContent = isSms ? '已绑定的手机号' : '注册邮箱';
        accountInput.placeholder = isSms ? '请输入 11 位手机号' : '请输入注册时填写的邮箱';
        accountInput.type = isSms ? 'tel' : 'email';
    }
    channelBtns.forEach(function (b) {
        b.addEventListener('click', function () {
            channel = b.dataset.channel;
            applyChannel();
            showMsg('', true);
        });
    });
    applyChannel();

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
        var account = accountInput.value.trim();
        if (channel === 'sms' && !/^1[3-9]\d{9}$/.test(account)) {
            showMsg('请输入正确的 11 位手机号', false);
            return;
        }
        if (channel === 'mail' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(account)) {
            showMsg('请输入正确的邮箱地址', false);
            return;
        }
        sendBtn.disabled = true;
        sendBtn.textContent = '发送中…';
        fetch('/forgot/code', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ channel: channel, account: account }),
        }).then(function (r) { return r.json(); }).then(function (data) {
            // 框架成功响应统一包装为 {code:0, data:{ok,msg}}，这里兼容解包
            var d = data && data.data ? data.data : data;
            showMsg(d.msg, d.ok);
            if (d.ok) {
                setCountdown(60);
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

    resetBtn.addEventListener('click', function () {
        var account = accountInput.value.trim();
        var code = codeInput.value.trim();
        var pwd = pwdInput.value;
        if (!account) { showMsg('请输入' + (channel === 'sms' ? '手机号' : '邮箱'), false); return; }
        if (!/^\d{6}$/.test(code)) { showMsg('请输入 6 位验证码', false); return; }
        if (pwd.length < 6) { showMsg('新密码至少 6 位', false); return; }
        resetBtn.disabled = true;
        resetBtn.textContent = '提交中…';
        fetch('/forgot/reset', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ channel: channel, account: account, code: code, password: pwd }),
        }).then(function (r) { return r.json(); }).then(function (data) {
            // 框架成功响应统一包装为 {code:0, data:{ok,msg}}，这里兼容解包
            var d = data && data.data ? data.data : data;
            showMsg(d.msg, d.ok);
            if (d.ok) {
                setTimeout(function () { window.location.href = '/login'; }, 1500);
            } else {
                resetBtn.disabled = false;
                resetBtn.textContent = '重置密码';
            }
        }).catch(function () {
            showMsg('网络错误，请稍后重试', false);
            resetBtn.disabled = false;
            resetBtn.textContent = '重置密码';
        });
    });
})();
</script>
<?php include __DIR__ . '/../_partials/footer.php'; ?>
