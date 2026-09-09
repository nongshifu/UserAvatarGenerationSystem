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
                <div class="avatar-card" id="av-<?= (int)$av['id'] ?>" style="position:relative">
                    <a href="/avatar/<?= (int)$av['id'] ?>">
                        <img loading="lazy" src="<?= $e($av['result_thumb_url'] ?: $av['result_url']) ?>" alt="头像 #<?= (int)$av['id'] ?>">
                    </a>
                    <button type="button" title="删除"
                            onclick="delAvatar(<?= (int)$av['id'] ?>, this)"
                            style="position:absolute;top:8px;right:8px;width:28px;height:28px;border-radius:8px;border:1px solid rgba(255,80,80,.5);background:rgba(30,10,15,.7);color:#ff8a8a;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:14px;line-height:1;backdrop-filter:blur(6px)">
                        ✕
                    </button>
                </div>
                <?php endforeach; ?>
            </div>
            <script>
            function delAvatar(id, btn) {
                if (!confirm('确认删除这张头像吗？删除后原图和结果图都会被清理，且无法恢复。')) return;
                btn.disabled = true;
                btn.style.opacity = '0.5';
                var fd = new FormData();
                fetch('/console/avatars/' + id + '/delete', {
                    method: 'POST',
                    body: fd,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                }).then(function(r) { return r.json().then(function(j){ return {ok:r.ok, data:j}; }); })
                  .then(function(ret) {
                    if (ret.ok && ret.data && ret.data.deleted) {
                        var card = document.getElementById('av-' + id);
                        if (card) {
                            card.style.transition = 'opacity .3s';
                            card.style.opacity = '0';
                            setTimeout(function() { card.remove(); }, 300);
                        }
                        showTip('头像已删除');
                    } else {
                        btn.disabled = false;
                        btn.style.opacity = '1';
                        showTip((ret.data && ret.data.message) ? ret.data.message : '删除失败');
                    }
                }).catch(function() {
                    btn.disabled = false;
                    btn.style.opacity = '1';
                    showTip('网络错误，请稍后重试');
                });
            }
            function showTip(msg) {
                var tip = document.createElement('div');
                tip.textContent = msg;
                tip.style.cssText = 'position:fixed;top:20px;left:50%;transform:translateX(-50%);padding:10px 18px;border-radius:10px;background:rgba(30,20,40,.92);border:1px solid rgba(0,212,255,.4);color:#7fe9ff;font-size:14px;z-index:9999;backdrop-filter:blur(8px)';
                document.body.appendChild(tip);
                setTimeout(function() { tip.remove(); }, 2200);
            }
            </script>
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
