<?php $active='index'; $balance=$balance ?? 0; include __DIR__ . '/../_partials/header.php'; ?>
<section class="container">
    <div class="console-layout">
        <?php include __DIR__ . '/../_partials/console_side.php'; ?>
        <div>
            <h2 style="margin-bottom:20px">控制台概览</h2>
            <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr))">
                <div class="card"><div class="muted" style="font-size:.85rem">积分余额</div><div style="font-size:1.8rem;font-weight:700;color:#7b5cff;margin-top:6px"><?= $e((string)$balance) ?></div></div>
                <div class="card"><div class="muted" style="font-size:.85rem">我的头像</div><div style="font-size:1.8rem;font-weight:700;margin-top:6px"><?= $e((string)$stats['avatar_count']) ?></div></div>
                <div class="card"><div class="muted" style="font-size:.85rem">生成记录</div><div style="font-size:1.8rem;font-weight:700;margin-top:6px"><?= $e((string)$stats['gen_count']) ?></div></div>
                <div class="card"><div class="muted" style="font-size:.85rem">API KEY</div><div style="font-size:1.8rem;font-weight:700;margin-top:6px"><?= $e((string)$stats['key_count']) ?></div></div>
            </div>
            <div class="card" style="margin-top:20px;text-align:center">
                <p class="muted" style="margin-bottom:12px">开始生成你的专属头像</p>
                <a href="/console/generate" class="btn">立即生成</a>
                <a href="/console/recharge" class="btn btn-outline">积分充值</a>
            </div>
        </div>
    </div>
</section>
<?php include __DIR__ . '/../_partials/footer.php'; ?>
