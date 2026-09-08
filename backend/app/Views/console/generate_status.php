<?php $active='generate'; include __DIR__ . '/../_partials/header.php'; ?>
<section class="container">
    <div class="console-layout">
        <?php include __DIR__ . '/../_partials/console_side.php'; ?>
        <div>
            <h2 style="margin-bottom:20px">生成结果</h2>
            <?php
                $st = (string)($record['status'] ?? 'pending');
                $statusText = ['pending'=>'排队中…','processing'=>'生成中…','success'=>'生成成功','failed'=>'生成失败'][$st] ?? $st;
                $statusColor = ['pending'=>'#ffc46f','processing'=>'#7b5cff','success'=>'#6fff9a','failed'=>'#ff8a8a'][$st] ?? '#aaa';
            ?>
            <div class="card" style="text-align:center">
                <p style="margin-bottom:16px">状态：<span style="color:<?= $statusColor ?>;font-weight:600"><?= $e($statusText) ?></span></p>
                <?php if ($avatar && $st==='success'): ?>
                    <img src="<?= $e($avatar['result_url']) ?>" alt="生成结果" style="max-width:100%;border-radius:12px;margin-bottom:16px">
                    <div>
                        <a href="<?= $e($avatar['result_url']) ?>" class="btn btn-sm" download>下载</a>
                        <a href="/avatar/<?= (int)$avatar['id'] ?>" class="btn btn-sm btn-outline">查看详情</a>
                    </div>
                <?php elseif ($st==='failed'): ?>
                    <p class="alert">生成失败：<?= $e((string)($record['error_msg'] ?? '')) ?></p>
                    <p>积分已自动退还</p>
                <?php else: ?>
                    <p class="muted">请稍候，<a href="" style="color:#7b5cff">刷新</a> 查看进度</p>
                    <script>setTimeout(()=>location.reload(),5000);</script>
                <?php endif; ?>
            </div>
            <p style="margin-top:16px;text-align:center"><a href="/console/generate" class="btn btn-outline btn-sm">再生成一张</a></p>
        </div>
    </div>
</section>
<?php include __DIR__ . '/../_partials/footer.php'; ?>
