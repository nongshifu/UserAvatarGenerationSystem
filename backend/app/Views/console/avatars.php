<?php $active='avatars'; $balance=$balance ?? 0; include __DIR__ . '/../_partials/header.php'; ?>
<section class="container">
    <div class="console-layout">
        <?php include __DIR__ . '/../_partials/console_side.php'; ?>
        <div>
            <h2 style="margin-bottom:20px">我的头像</h2>
            <?php if (!empty($list)): ?>
            <div class="grid avatar-grid">
                <?php foreach ($list as $av): ?>
                <a class="avatar-card" href="/avatar/<?= (int)$av['id'] ?>">
                    <img loading="lazy" src="<?= $e($av['result_thumb_url'] ?: $av['result_url']) ?>" alt="头像 #<?= (int)$av['id'] ?>">
                </a>
                <?php endforeach; ?>
            </div>
            <div class="pagination">
                <?php for ($i=1; $i<=$pagination['last_page']; $i++): ?>
                <a class="<?= $i===$pagination['page']?'active':'' ?>" href="?page=<?= $i ?>"><?= $i ?></a>
                <?php endfor; ?>
            </div>
            <?php else: ?>
            <p class="muted">还没有头像，<a href="/console/generate" style="color:#7b5cff">去生成一张</a></p>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php include __DIR__ . '/../_partials/footer.php'; ?>
