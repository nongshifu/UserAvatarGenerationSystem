<?php $active='recharge'; include __DIR__ . '/../_partials/header.php'; ?>
<section class="container">
    <div class="console-layout">
        <?php include __DIR__ . '/../_partials/console_side.php'; ?>
        <div>
            <h2 style="margin-bottom:20px">积分充值</h2>
            <?php if ($error): ?><div class="alert"><?= $e($error) ?></div><?php endif; ?>
            <p class="muted" style="margin-bottom:16px">当前余额 <b style="color:#7b5cff"><?= $e((string)$balance) ?></b> 积分</p>
            <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr))">
                <?php foreach ($products as $p):
                    $bonus=(int)$p['bonus_points'];
                ?>
                <form method="post" action="/console/recharge">
                    <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                    <div class="card" style="text-align:center">
                        <h3 style="margin-bottom:8px"><?= $e((string)$p['name']) ?></h3>
                        <div style="font-size:2rem;font-weight:700;color:#7b5cff;margin:10px 0">¥<?= $e((string)$p['price']) ?></div>
                        <p class="muted"><?= (int)$p['points'] ?> 积分<?php if($bonus>0): ?> + 赠 <?= $bonus ?><?php endif; ?></p>
                        <button type="submit" name="pay_method" value="manual" class="btn btn-sm" style="margin-top:12px">手动支付</button>
                        <button type="submit" name="pay_method" value="wechat" class="btn btn-sm btn-outline" style="margin-top:12px">微信</button>
                        <button type="submit" name="pay_method" value="alipay" class="btn btn-sm btn-outline" style="margin-top:12px">支付宝</button>
                    </div>
                </form>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>
<?php include __DIR__ . '/../_partials/footer.php'; ?>
