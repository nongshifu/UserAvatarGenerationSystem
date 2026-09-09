<?php $active='avatars'; $balance=$balance ?? 0; include __DIR__ . '/../_partials/header.php'; ?>
<section class="container">
    <div class="console-layout">
        <?php include __DIR__ . '/../_partials/console_side.php'; ?>
        <div>
            <h2 style="margin-bottom:20px">我的头像</h2>
            <?php if (!empty($_GET['deleted'])): ?>
            <div style="margin-bottom:16px;padding:10px 14px;border-radius:10px;background:rgba(0,212,255,.12);border:1px solid rgba(0,212,255,.35);color:#7fe9ff;font-size:14px">头像已删除，对应图片文件已清理。</div>
            <?php endif; ?>
            <?php if (!empty($list)): ?>
            <div class="grid avatar-grid">
                <?php foreach ($list as $av): ?>
                <div class="avatar-card" style="position:relative">
                    <a href="/avatar/<?= (int)$av['id'] ?>">
                        <img loading="lazy" src="<?= $e($av['result_thumb_url'] ?: $av['result_url']) ?>" alt="头像 #<?= (int)$av['id'] ?>">
                    </a>
                    <form method="post" action="/console/avatars/<?= (int)$av['id'] ?>/delete"
                          onsubmit="return confirm('确认删除这张头像吗？删除后原图和结果图都会被清理，且无法恢复。');"
                          style="position:absolute;top:8px;right:8px;margin:0">
                        <input type="hidden" name="id" value="<?= (int)$av['id'] ?>">
                        <button type="submit" title="删除"
                                style="width:28px;height:28px;border-radius:8px;border:1px solid rgba(255,80,80,.5);background:rgba(30,10,15,.7);color:#ff8a8a;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:14px;line-height:1;backdrop-filter:blur(6px)">
                            ✕
                        </button>
                    </form>
                </div>
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
