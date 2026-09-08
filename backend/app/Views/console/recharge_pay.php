<?php $active='recharge'; include __DIR__ . '/../_partials/header.php'; ?>
<section class="container">
    <div class="console-layout">
        <?php include __DIR__ . '/../_partials/console_side.php'; ?>
        <div>
            <h2 style="margin-bottom:20px">请扫码支付</h2>
            <div class="card" style="text-align:center">
                <p>订单号 <?= $e((string)$order['order_no']) ?></p>
                <p style="font-size:1.6rem;font-weight:700;color:#7b5cff;margin:8px 0">¥<?= $e((string)$order['amount']) ?></p>
                <?php if ($codeUrl): ?>
                    <div style="display:flex;justify-content:center;margin:16px 0">
                        <svg id="payQr" style="background:#fff;padding:10px;border-radius:8px;max-width:240px"></svg>
                    </div>
                    <p class="muted">请使用 <?= $e($payMethod === 'alipay' ? '支付宝' : '微信') ?> 扫码支付</p>
                    <details style="margin-top:12px;text-align:left">
                        <summary class="muted" style="cursor:pointer">二维码无法识别？</summary>
                        <p style="margin-top:8px;word-break:break-all"><code><?= $e($codeUrl) ?></code></p>
                    </details>
                    <script src="/static/js/qr.js"></script>
                    <script>
                        try { window.qrcode.render(<?= json_encode($codeUrl) ?>, document.getElementById('payQr'), 'L'); } catch(e){
                            document.getElementById('payQr').outerHTML = '<p class="muted">二维码生成失败，请复制下方链接</p>';
                        }
                        setTimeout(()=>location.href='/console/recharge/return',12000);
                    </script>
                <?php elseif ($payQrcode): ?>
                    <img src="<?= $e($payQrcode) ?>" alt="支付二维码" style="max-width:240px;border-radius:8px;background:#fff;padding:10px">
                <?php else: ?>
                    <p class="alert">支付渠道暂未配置，请联系客服进行手动核销。</p>
                    <p class="muted">支付方式：<?= $e($payMethod) ?></p>
                    <a href="/console/orders" class="btn btn-outline btn-sm">查看订单</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
<?php include __DIR__ . '/../_partials/footer.php'; ?>
