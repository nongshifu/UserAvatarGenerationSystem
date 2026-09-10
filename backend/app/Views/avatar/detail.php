<?php
$img = $avatar['result_url'] ?? '';
$thumb = $avatar['result_thumb_url'] ?: $img;
$baseUrl = $siteName;
$compare = !empty($isOwner) && !empty($originUrl);
?>
<?php include __DIR__ . '/../_partials/header.php'; ?>
<style>
.imgv-trigger{cursor:zoom-in}
/* 图片查看器模态 */
.imgv{position:fixed;inset:0;z-index:9999;background:rgba(5,8,20,.92);backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);display:flex;flex-direction:column;animation:imgv-in .18s ease}
@keyframes imgv-in{from{opacity:0}to{opacity:1}}
.imgv-top{display:flex;align-items:center;gap:10px;padding:12px 16px;color:#fff;flex-wrap:wrap}
.imgv-label{font-size:.9rem;font-weight:600;opacity:.92;white-space:nowrap}
.imgv-switch{display:flex;gap:6px}
.imgv-switch button{padding:5px 14px;border-radius:16px;border:1px solid rgba(255,255,255,.25);background:rgba(255,255,255,.08);color:rgba(255,255,255,.75);font-size:.8rem;cursor:pointer;transition:all .15s}
.imgv-switch button.on{background:linear-gradient(90deg,#7b5cff,#00d4ff);border-color:transparent;color:#fff;font-weight:600}
.imgv-spacer{flex:1}
.imgv-btn{min-width:36px;height:36px;padding:0 10px;border-radius:18px;border:1px solid rgba(255,255,255,.25);background:rgba(255,255,255,.08);color:#fff;font-size:1rem;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;transition:background .15s}
.imgv-btn:hover{background:rgba(255,255,255,.18)}
.imgv-stage{flex:1;position:relative;overflow:hidden;touch-action:none;cursor:grab}
.imgv-stage.zoomed{cursor:grabbing}
.imgv-img{max-width:calc(100% - 32px);max-height:100%;user-select:none;-webkit-user-select:none;-webkit-user-drag:none;will-change:transform;border-radius:8px;box-shadow:0 12px 48px rgba(0,0,0,.5)}
.imgv-hint{position:absolute;left:0;right:0;bottom:14px;text-align:center;color:rgba(255,255,255,.45);font-size:.75rem;pointer-events:none;padding:0 16px}
@media (max-width:600px){.imgv-label{font-size:.82rem}.imgv-switch button{padding:4px 11px}}
/* 历史生成快捷切换 */
.hist-head{display:flex;align-items:baseline;gap:10px;margin-bottom:10px;flex-wrap:wrap}
.hist-row{display:flex;gap:10px;overflow-x:auto;padding:2px 2px 8px;scroll-snap-type:x proximity;-webkit-overflow-scrolling:touch}
.hist-row::-webkit-scrollbar{height:6px}
.hist-row::-webkit-scrollbar-thumb{background:rgba(255,255,255,.16);border-radius:3px}
.hist-thumb{position:relative;flex:0 0 auto;width:76px;height:76px;border-radius:12px;overflow:hidden;border:2px solid rgba(255,255,255,.14);background:rgba(255,255,255,.06);scroll-snap-align:start;transition:border-color .15s,transform .15s;display:block}
.hist-thumb img{width:100%;height:100%;object-fit:cover;display:block;background:rgba(0,0,0,.3)}
.hist-thumb:hover{border-color:rgba(139,115,255,.7);transform:translateY(-2px)}
.hist-thumb.active{border-color:#8b73ff;box-shadow:0 0 0 3px rgba(123,92,255,.3)}
.hist-thumb.active::after{content:'当前';position:absolute;left:0;right:0;bottom:0;font-size:.62rem;line-height:1.5;text-align:center;background:linear-gradient(90deg,rgba(123,92,255,.95),rgba(0,212,255,.95));color:#fff;font-weight:600}
</style>
<script type="application/ld+json">
{
  "@context":"https://schema.org",
  "@type":"ImageObject",
  "contentUrl":"<?= $e($img) ?>",
  "thumbnail":"<?= $e($thumb) ?>",
  "name":"AI 头像 #<?= (int)$avatar['id'] ?>",
  "description":"<?= $e($description) ?>"
}
</script>
<section class="container section" style="max-width:760px">
    <div class="card" style="text-align:center">
        <?php if ($compare): ?>
        <div style="display:flex;gap:16px;justify-content:center;flex-wrap:wrap">
            <figure style="margin:0;flex:1 1 220px;max-width:320px">
                <img src="<?= $e($originUrl) ?>" alt="参考原图"
                     class="imgv-trigger" data-viewer-src="<?= $e($originUrl) ?>" data-viewer-label="参考原图"
                     style="width:100%;border-radius:12px;border:1px solid rgba(255,255,255,.14);background:rgba(0,0,0,.3)"
                     onerror="this.closest('figure').style.display='none';this.closest('.card').querySelector('[data-compare-wrap]').style.display='block'">
                <figcaption class="muted" style="font-size:.82rem;margin-top:8px">参考原图</figcaption>
            </figure>
            <figure style="margin:0;flex:1 1 220px;max-width:320px">
                <img src="<?= $e($img) ?>" alt="AI 头像 #<?= (int)$avatar['id'] ?>"
                     class="imgv-trigger" data-viewer-src="<?= $e($img) ?>" data-viewer-label="生成结果"
                     style="width:100%;border-radius:12px;border:1px solid rgba(255,255,255,.14);background:rgba(0,0,0,.3)">
                <figcaption class="muted" style="font-size:.82rem;margin-top:8px">生成结果</figcaption>
            </figure>
        </div>
        <div data-compare-wrap hidden>
            <img src="<?= $e($img) ?>" alt="AI 头像 #<?= (int)$avatar['id'] ?>"
                 class="imgv-trigger" data-viewer-src="<?= $e($img) ?>" data-viewer-label="生成结果"
                 style="max-width:100%;border-radius:12px">
        </div>
        <?php else: ?>
        <img src="<?= $e($img) ?>" alt="AI 头像 #<?= (int)$avatar['id'] ?>"
             class="imgv-trigger" data-viewer-src="<?= $e($img) ?>" data-viewer-label="生成结果"
             style="max-width:100%;border-radius:12px">
        <?php endif; ?>
        <h2 style="margin:16px 0 8px">头像 #<?= (int)$avatar['id'] ?></h2>
        <p class="muted">浏览量 <?= (int)$avatar['views'] ?> · 生成于 <?= $e((string)($avatar['created_at'] ?? '')) ?></p>
        <div style="margin-top:18px">
            <a href="<?= $e($img) ?>" class="btn btn-sm" download>下载</a>
            <?php if ($compare): ?>
            <a href="/console/generate?use_image=<?= urlencode($originUrl) ?>" class="btn btn-sm btn-outline" title="跳转后自动选择原图，需手动确认生成">用原图重新生成</a>
            <?php else: ?>
            <a href="/console/generate" class="btn btn-sm btn-outline">我也生成</a>
            <?php endif; ?>
            <?php if (!empty($isOwner)): ?>
            <form method="post" action="/console/avatars/<?= (int)$avatar['id'] ?>/delete"
                  onsubmit="return confirm('确认删除这张头像吗？删除后原图和结果图都会被清理，且无法恢复。');"
                  style="display:inline;margin-left:8px">
                <button type="submit" class="btn btn-sm" style="border-color:rgba(255,80,80,.5);color:#ff8a8a">删除</button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($historyAvatars)): ?>
    <div class="card" style="margin-top:16px">
        <div class="hist-head">
            <span style="font-weight:600">历史生成</span>
            <span class="muted" style="font-size:.8rem">点击缩略图直接切换，无需返回列表</span>
        </div>
        <div class="hist-row">
            <?php foreach ($historyAvatars as $h): ?>
            <a class="hist-thumb<?= $h['id'] === (int)$avatar['id'] ? ' active' : '' ?>"
               href="/avatar/<?= (int)$h['id'] ?>" title="头像 #<?= (int)$h['id'] ?>">
                <img src="<?= $e($h['thumb']) ?>" alt="头像 #<?= (int)$h['id'] ?>" loading="lazy"
                     onerror="this.parentElement.remove()">
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</section>
<script>
(function () {
    var triggers = Array.prototype.slice.call(document.querySelectorAll('.imgv-trigger'));
    if (!triggers.length) return;
    var items = triggers.map(function (t) {
        return { src: t.getAttribute('data-viewer-src'), label: t.getAttribute('data-viewer-label') || '图片' };
    });
    triggers.forEach(function (t, i) {
        t.addEventListener('click', function () { openViewer(i); });
    });

    function openViewer(startIdx) {
        if (document.querySelector('.imgv')) return;
        var isTouch = ('ontouchstart' in window) || (navigator.maxTouchPoints || 0) > 0;

        var ov = document.createElement('div'); ov.className = 'imgv';
        var top = document.createElement('div'); top.className = 'imgv-top';
        var label = document.createElement('span'); label.className = 'imgv-label';
        top.appendChild(label);

        // 多图切换（参考原图 / 生成结果）
        var swBtns = [];
        if (items.length > 1) {
            var sw = document.createElement('div'); sw.className = 'imgv-switch';
            items.forEach(function (it, i) {
                var b = document.createElement('button');
                b.type = 'button'; b.textContent = it.label;
                b.addEventListener('click', function () { setImage(i); });
                sw.appendChild(b); swBtns.push(b);
            });
            top.appendChild(sw);
        }

        var spacer = document.createElement('div'); spacer.className = 'imgv-spacer'; top.appendChild(spacer);
        function mkBtn(txt, fn, aria) {
            var b = document.createElement('button');
            b.type = 'button'; b.className = 'imgv-btn'; b.textContent = txt;
            b.setAttribute('aria-label', aria || txt);
            b.addEventListener('click', fn);
            return b;
        }
        var stage = document.createElement('div'); stage.className = 'imgv-stage';
        top.appendChild(mkBtn('－', function () { zoomAt(stageCenter(), 1 / 1.4); }, '缩小'));
        top.appendChild(mkBtn('＋', function () { zoomAt(stageCenter(), 1.4); }, '放大'));
        top.appendChild(mkBtn('重置', function () { reset(); }, '重置缩放'));
        top.appendChild(mkBtn('×', close, '关闭'));

        var img = document.createElement('img');
        img.className = 'imgv-img'; img.draggable = false; img.alt = '';
        stage.appendChild(img);

        var hint = document.createElement('div'); hint.className = 'imgv-hint';
        hint.textContent = isTouch ? '双指或双击缩放 · 拖动移动 · 点空白处关闭' : '滚轮或双击缩放 · 拖动移动 · Esc 关闭';

        ov.appendChild(top); ov.appendChild(stage); ov.appendChild(hint);
        document.body.appendChild(ov);
        var prevOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';

        // ── 变换状态 ──
        var scale = 1, tx = 0, ty = 0;
        function apply() {
            img.style.transform = 'translate(' + tx + 'px,' + ty + 'px) scale(' + scale + ')';
            stage.classList.toggle('zoomed', scale > 1);
        }
        function reset() { scale = 1; tx = 0; ty = 0; apply(); }
        function stageCenter() { return { x: stage.clientWidth / 2, y: stage.clientHeight / 2 }; }
        function local(e) {
            var r = stage.getBoundingClientRect();
            return { x: e.clientX - r.left, y: e.clientY - r.top };
        }
        function dist(a, b) { var dx = a.x - b.x, dy = a.y - b.y; return Math.sqrt(dx * dx + dy * dy); }
        function zoomAt(p, k) {
            var ns = Math.min(8, Math.max(1, scale * k));
            if (ns === scale) return;
            var kk = ns / scale, c = stageCenter();
            var dx = p.x - c.x - tx, dy = p.y - c.y - ty;
            tx = p.x - c.x - dx * kk;
            ty = p.y - c.y - dy * kk;
            scale = ns;
            if (scale === 1) { tx = 0; ty = 0; }
            apply();
        }

        function setImage(i) {
            if (!items[i]) return;
            img.onerror = function () { hint.textContent = '图片加载失败'; };
            img.src = items[i].src;
            label.textContent = items[i].label;
            swBtns.forEach(function (b, j) { b.classList.toggle('on', j === i); });
            reset();
        }
        setImage(startIdx);

        // ── 指针手势（统一鼠标 / 触屏） ──
        var pts = new Map();          // pointerId -> {x,y,moved,target}
        var lastTapT = 0, lastTapP = null;

        stage.addEventListener('pointerdown', function (e) {
            if (e.button) return; // 非左键
            stage.setPointerCapture(e.pointerId);
            var p = local(e);
            pts.set(e.pointerId, { x: p.x, y: p.y, moved: 0, target: e.target });
            e.preventDefault();
        });

        stage.addEventListener('pointermove', function (e) {
            var cur = pts.get(e.pointerId);
            if (!cur) return;
            var p = local(e);
            var prev = { x: cur.x, y: cur.y };

            if (pts.size === 1) {
                cur.moved += dist(p, prev);
                if (scale > 1) { tx += p.x - prev.x; ty += p.y - prev.y; apply(); }
                cur.x = p.x; cur.y = p.y;
            } else if (pts.size >= 2) {
                // 双指捏合：先取两指旧位置算距离，再更新当前指
                var arrOld = Array.from(pts.values());
                var dOld = dist(arrOld[0], arrOld[1]);
                cur.x = p.x; cur.y = p.y;
                var arrNew = Array.from(pts.values());
                var dNew = dist(arrNew[0], arrNew[1]);
                if (dOld > 0 && dNew > 0) {
                    zoomAt({ x: (arrNew[0].x + arrNew[1].x) / 2, y: (arrNew[0].y + arrNew[1].y) / 2 }, dNew / dOld);
                }
            }
        });

        function pointerEnd(e) {
            var cur = pts.get(e.pointerId);
            if (!cur) return;
            var wasSingle = pts.size === 1;
            pts.delete(e.pointerId);
            if (!wasSingle || cur.moved > 8) return;

            var p = local(e);
            // 点空白处（scale=1 时）关闭
            if (scale === 1 && cur.target === stage) { close(); return; }
            // 双击 / 双击切换 1x ↔ 2.5x
            var now = Date.now();
            if (lastTapT && now - lastTapT < 320 && lastTapP && dist(p, lastTapP) < 48) {
                if (scale > 1) reset(); else zoomAt(p, 2.5);
                lastTapT = 0;
            } else {
                lastTapT = now; lastTapP = p;
            }
        }
        stage.addEventListener('pointerup', pointerEnd);
        stage.addEventListener('pointercancel', pointerEnd);

        // 滚轮缩放
        stage.addEventListener('wheel', function (e) {
            e.preventDefault();
            zoomAt(local(e), e.deltaY < 0 ? 1.15 : 1 / 1.15);
        }, { passive: false });

        // 屏蔽 iOS Safari 手势默认行为与长按菜单
        ['gesturestart', 'gesturechange', 'gestureend'].forEach(function (ev) {
            ov.addEventListener(ev, function (e) { e.preventDefault(); });
        });
        stage.addEventListener('contextmenu', function (e) { e.preventDefault(); });

        // 关闭
        function onKey(e) { if (e.key === 'Escape') close(); }
        document.addEventListener('keydown', onKey);
        function close() {
            document.removeEventListener('keydown', onKey);
            document.body.style.overflow = prevOverflow;
            ov.remove();
        }
    }
})();
</script>
<?php include __DIR__ . '/../_partials/footer.php'; ?>
