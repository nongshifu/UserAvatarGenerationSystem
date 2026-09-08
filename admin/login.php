<?php
// 登录页（资源缓存开关与 index.php 保持一致）
$ASSET_DEBUG = true;
$ASSET_VER   = $ASSET_DEBUG ? (string)time() : '1.0.0';
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登录 · 头像引擎后台</title>
    <link rel="stylesheet" href="/admin/assets/app.css?v=<?= $ASSET_VER ?>">
</head>
<body>
<div class="login-wrap">
    <div class="login-box">
        <h1>头像引擎后台</h1>
        <div class="login-error" id="err"></div>
        <div class="form-row">
            <input id="username" type="text" placeholder="用户名" autocomplete="username" autofocus>
        </div>
        <div class="form-row">
            <input id="password" type="password" placeholder="密码" autocomplete="current-password">
        </div>
        <button class="btn-primary" id="loginBtn">登录</button>
    </div>
</div>
<script src="/admin/assets/app.js?v=<?= $ASSET_VER ?>"></script>
<script>
  const $err = $('#err');
  async function doLogin() {
    $err.textContent = '';
    const username = $('#username').value.trim();
    const password = $('#password').value;
    if (!username || !password) { $err.textContent = '请输入用户名和密码'; return; }
    try {
      const data = await api('/login', { method: 'POST', body: { username, password } });
      Storage.setToken(data.token);
      Storage.setUser(data.user);
      location.href = '/admin/';
    } catch (e) {
      $err.textContent = e.message;
    }
  }
  $('#loginBtn').addEventListener('click', doLogin);
  $('#password').addEventListener('keydown', e => { if (e.key === 'Enter') doLogin(); });
</script>
</body>
</html>
