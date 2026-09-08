<?php $active='recharge'; include __DIR__ . '/../_partials/header.php'; ?>
<section class="container">
    <div class="console-layout">
        <?php include __DIR__ . '/../_partials/console_side.php'; ?>
        <div>
            <div class="card" style="text-align:center">
                <h2 style="margin-bottom:12px;color:#6fff9a">支付完成</h2>
                <p class="muted" style="margin-bottom:18px">当前余额 <b style="color:#7b5cff"><?= $e((string)$balance) ?></b> 积分</p>
                <a href="/console/generate" class="btn">立即生成头像</a>
                <a href="/console/orders" class="btn btn-outline">查看订单</a>
            </div>
        </div>
    </div>
</section>
<?php include __DIR__ . '/../_partials/footer.php'; ?>
