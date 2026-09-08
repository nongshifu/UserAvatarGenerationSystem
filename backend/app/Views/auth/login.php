<?php include __DIR__ . '/../_partials/header.php'; ?>
<section class="container section">
    <div class="form-card card">
        <h2 style="margin-bottom:18px;text-align:center">登录</h2>
        <?php if (!empty($error)): ?><div class="alert"><?= $e($error) ?></div><?php endif; ?>
        <form method="post" action="/login">
            <label>用户名 / 邮箱</label>
            <input type="text" name="username" value="<?= $e($username) ?>" required autofocus>
            <label>密码</label>
            <input type="password" name="password" required>
            <button type="submit" class="btn" style="width:100%;margin-top:16px">登录</button>
        </form>
        <p class="muted" style="text-align:center;margin-top:14px">
            <a href="/forgot" style="color:#7b5cff">忘记密码？</a>
            &nbsp;·&nbsp;
            还没账号？<a href="/register" style="color:#7b5cff">立即注册</a>
        </p>
    </div>
</section>
<?php include __DIR__ . '/../_partials/footer.php'; ?>
