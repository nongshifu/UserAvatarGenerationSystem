<?php include __DIR__ . '/../_partials/header.php'; ?>
<section class="container section">
    <div class="form-card card">
        <h2 style="margin-bottom:6px;text-align:center">注册</h2>
        <p class="muted" style="text-align:center;margin-bottom:18px">注册即送 <b style="color:#7b5cff"><?= $e((string)$registerBonus) ?></b> 积分</p>
        <?php if (!empty($error)): ?><div class="alert"><?= $e($error) ?></div><?php endif; ?>
        <form method="post" action="/register">
            <label>用户名（3-50 位）</label>
            <input type="text" name="username" value="<?= $e($username) ?>" required minlength="3" maxlength="50" autofocus>
            <label>邮箱</label>
            <input type="email" name="email" value="<?= $e($email) ?>" required>
            <label>密码（≥6 位）</label>
            <input type="password" name="password" required minlength="6">
            <label>确认密码</label>
            <input type="password" name="password_confirm" required minlength="6">
            <button type="submit" class="btn" style="width:100%;margin-top:16px">注册</button>
        </form>
        <p class="muted" style="text-align:center;margin-top:14px">
            已有账号？<a href="/login" style="color:#7b5cff">去登录</a>
        </p>
    </div>
</section>
<?php include __DIR__ . '/../_partials/footer.php'; ?>
