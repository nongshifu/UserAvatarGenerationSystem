<?php
$active = 'generations';
include __DIR__ . '/../_partials/header.php';
?>
<style>
@keyframes gen-dot-pulse{0%,100%{opacity:.3}50%{opacity:1}}
.gen-dot{display:inline-block;width:6px;height:6px;border-radius:50%;background:#ffd666;margin-left:7px;vertical-align:middle;animation:gen-dot-pulse 1s infinite}
/* 预览缩略图 */
.gen-thumb{width:44px;height:44px;border-radius:8px;object-fit:cover;display:block;border:1px solid rgba(255,255,255,.16);background:rgba(255,255,255,.05);cursor:zoom-in;transition:transform .15s,border-color .15s}
.gen-thumb:hover{transform:scale(1.12);border-color:rgba(139,115,255,.8)}
.gen-thumb-ph{width:44px;height:44px;border-radius:8px;display:flex;align-items:center;justify-content:center;border:1px dashed rgba(255,255,255,.16);color:rgba(255,255,255,.3);font-size:1rem;background:rgba(255,255,255,.03)}
.gen-thumb-deleted{width:44px;height:44px;border-radius:8px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px;border:1px dashed rgba(255,120,120,.3);color:rgba(255,160,160,.55);font-size:.6rem;background:rgba(255,80,80,.05)}
/* 轻量预览模态 */
.gv{position:fixed;inset:0;z-index:9999;background:rgba(5,8,20,.92);backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);display:flex;align-items:center;justify-content:center;animation:gv-in .16s ease}
@keyframes gv-in{from{opacity:0}to{opacity:1}}
.gv img{max-width:calc(100vw - 48px);max-height:calc(100vh - 96px);border-radius:12px;box-shadow:0 12px 48px rgba(0,0,0,.5)}
.gv-x{position:fixed;top:16px;right:16px;width:38px;height:38px;border-radius:50%;border:1px solid rgba(255,255,255,.25);background:rgba(255,255,255,.08);color:#fff;font-size:1.1rem;cursor:pointer}
.gv-x:hover{background:rgba(255,255,255,.18)}
/* 生成走势图表（样式与积分记录页一致） */
.pt-chips{display:flex;gap:6px;flex-wrap:wrap}
.pt-chip{display:inline-flex;align-items:center;gap:6px;padding:4px 12px;border-radius:14px;border:1px solid rgba(255,255,255,.18);background:rgba(255,255,255,.05);color:rgba(255,255,255,.72);font-size:.78rem;cursor:pointer;transition:all .15s}
.pt-chip i{width:8px;height:8px;border-radius:50%;display:inline-block}
.pt-chip:hover{border-color:rgba(139,115,255,.6);color:#fff}
.pt-chip.active{background:linear-gradient(90deg,rgba(123,92,255,.35),rgba(0,212,255,.3));border-color:rgba(139,115,255,.8);color:#fff;font-weight:600}
.pt-summary{font-size:.78rem;margin-bottom:6px}
.pt-summary b{color:#fff}
.pt-tip{position:absolute;pointer-events:none;background:rgba(10,14,30,.95);border:1px solid rgba(139,115,255,.5);border-radius:8px;padding:7px 10px;font-size:.75rem;color:#fff;box-shadow:0 6px 20px rgba(0,0,0,.4);white-space:nowrap;z-index:5}
</style>
<?php

/** @var array $list 生成记录 */
/** @var array $pagination 分页信息 */
/** @var array $filters 当前筛选条件 */
/** @var array $styleMap 风格ID=>名称 */

$stMap = [
    'pending'    => '排队中',
    'processing' => '生成中',
    'success'    => '已完成',
    'failed'     => '失败',
];
// 状态徽章颜色：进行中琥珀色、成功绿色、失败红色、排队紫
$stStyle = [
    'pending'    => 'background:rgba(123,92,255,.18);border-color:rgba(123,92,255,.45);color:#b7a8ff',
    'processing' => 'background:rgba(255,193,7,.14);border-color:rgba(255,193,7,.4);color:#ffd666',
    'success'    => 'background:rgba(40,200,120,.18);border-color:rgba(40,200,120,.4);color:#6fff9a',
    'failed'     => 'background:rgba(255,80,80,.15);border-color:rgba(255,80,80,.35);color:#ff8a8a',
];

// 是否有进行中的任务（用于自动刷新）
$hasActive = false;
foreach ($list as $r) {
    if (in_array($r['status'], ['pending', 'processing'], true)) {
        $hasActive = true;
        break;
    }
}

// 分页链接（保留搜索条件）
$pageUrl = function (int $p) use ($filters): string {
    $q = array_filter($filters, fn($v) => $v !== '' && $v !== null);
    $q['page'] = $p;
    return '?' . http_build_query($q);
};
?>
<section class="container">
    <div class="console-layout">
        <?php include __DIR__ . '/../_partials/console_side.php'; ?>
        <div>
            <h2 style="margin-bottom:6px">生成记录</h2>
            <p class="muted" style="margin-bottom:16px">图生图任务进度与历史；排队/生成中的任务本页会自动刷新。</p>

            <!-- 生成走势图表 -->
            <div class="card" style="margin-bottom:18px">
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:10px">
                    <span style="font-weight:600">生成走势</span>
                    <div class="pt-chips" id="gcMetrics">
                        <button type="button" class="pt-chip active" data-m="total"><i style="background:#8b73ff"></i>生成数</button>
                        <button type="button" class="pt-chip" data-m="success"><i style="background:#6fff9a"></i>成功</button>
                        <button type="button" class="pt-chip" data-m="failed"><i style="background:#ff8a8a"></i>失败</button>
                        <button type="button" class="pt-chip" data-m="points"><i style="background:#00d4ff"></i>消耗积分</button>
                    </div>
                    <div style="flex:1"></div>
                    <div class="pt-chips" id="gcRanges">
                        <button type="button" class="pt-chip" data-r="7">近7天</button>
                        <button type="button" class="pt-chip active" data-r="30">近30天</button>
                        <button type="button" class="pt-chip" data-r="90">近90天</button>
                        <button type="button" class="pt-chip" data-r="0">全部</button>
                    </div>
                </div>
                <div class="pt-summary muted" id="gcSummary"></div>
                <div style="position:relative" id="gcWrap">
                    <canvas id="gcChart" style="width:100%;height:240px;display:block;touch-action:pan-y"></canvas>
                    <div class="pt-tip" id="gcTip" hidden></div>
                </div>
                <div class="muted" id="gcEmpty" hidden style="text-align:center;padding:30px 0">暂无生成数据</div>
            </div>

            <!-- 搜索栏 -->
            <form method="get" action="/console/generations" style="display:flex;gap:10px;align-items:center;margin-bottom:18px;flex-wrap:wrap">
                <input type="text" name="q" value="<?= $e((string)($filters['keyword'] ?? '')) ?>" placeholder="任务ID 或失败原因关键词" style="flex:1;min-width:200px;max-width:320px">
                <div style="width:150px">
                    <select name="status">
                        <option value="">全部状态</option>
                        <?php foreach (['pending' => '排队中', 'processing' => '生成中', 'success' => '已完成', 'failed' => '失败'] as $sv => $sl): ?>
                        <option value="<?= $sv ?>" <?= ($filters['status'] ?? '') === $sv ? 'selected' : '' ?>><?= $sl ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn" style="margin:0">搜索</button>
                <?php if (($filters['keyword'] ?? '') !== '' || ($filters['status'] ?? '') !== ''): ?>
                <a class="btn btn-outline" href="/console/generations" style="margin:0">重置</a>
                <?php endif; ?>
            </form>

            <?php if ($hasActive): ?>
            <div class="alert" style="background:rgba(255,193,7,.1);border-color:rgba(255,193,7,.35);color:#ffd666;margin-bottom:14px">
                ⏳ 有任务正在排队/生成中，页面每 5 秒自动刷新…
            </div>
            <?php endif; ?>

            <?php if (!empty($list)): ?>
            <table class="table">
                <thead><tr><th>预览</th><th>任务ID</th><th>状态</th><th>风格</th><th>消耗</th><th>时间</th><th>说明 / 操作</th></tr></thead>
                <tbody>
                <?php foreach ($list as $r):
                    $st = (string)$r['status'];
                    $styleName = $styleMap[(int)($r['style_id'] ?? 0)] ?? '默认';
                    $avatarId = (int)($r['avatar_id'] ?? 0);
                    $thumb = $avatarId > 0 ? (string)($avatarThumbs[$avatarId] ?? '') : '';
                    $full = $avatarId > 0 ? (string)($avatarFulls[$avatarId] ?? '') : '';
                    // 已成功且关联头像，但 avatars 记录已不存在 → 头像已被删除
                    $deleted = ($st === 'success' && $avatarId > 0 && $thumb === '');
                ?>
                <tr>
                    <td>
                        <?php if ($deleted): ?>
                        <div class="gen-thumb-deleted" title="头像已删除">🗑<span>已删除</span></div>
                        <?php elseif ($thumb !== ''): ?>
                        <img src="<?= $e($thumb) ?>" class="gen-thumb" alt="生成结果预览"
                             data-full="<?= $e($full !== '' ? $full : $thumb) ?>"
                             loading="lazy" onerror="this.outerHTML='<div class=&quot;gen-thumb-deleted&quot; title=&quot;头像已删除&quot;>🗑<span>已删除</span></div>'">
                        <?php elseif ($st === 'pending' || $st === 'processing'): ?>
                        <div class="gen-thumb-ph" title="生成中">⏳</div>
                        <?php elseif ($st === 'failed'): ?>
                        <div class="gen-thumb-ph" title="生成失败">✕</div>
                        <?php else: ?>
                        <div class="gen-thumb-ph">·</div>
                        <?php endif; ?>
                    </td>
                    <td>#<?= (int)$r['id'] ?></td>
                    <td>
                        <span class="badge" style="<?= $stStyle[$st] ?? '' ?>">
                            <?= $e($stMap[$st] ?? $st) ?>
                            <?php if ($st === 'processing'): ?><span class="gen-dot"></span><?php endif; ?>
                        </span>
                    </td>
                    <td><?= $e((string)$styleName) ?></td>
                    <td><?= (int)$r['cost_points'] ?> 积分</td>
                    <td class="muted" style="white-space:nowrap"><?= $e((string)$r['created_at']) ?></td>
                    <td>
                        <?php if ($deleted): ?>
                            <span class="muted" title="头像已被删除，文件不可恢复" style="color:rgba(255,160,160,.5)">头像已删除</span>
                        <?php elseif ($st === 'success' && $avatarId > 0): ?>
                            <a href="/avatar/<?= $avatarId ?>" style="color:#7fe0ff">查看头像 →</a>
                        <?php elseif ($st === 'failed'): ?>
                            <span style="color:#ff8a8a" title="<?= $e((string)($r['error_msg'] ?? '')) ?>">
                                <?= $e(mb_substr((string)($r['error_msg'] ?? '生成失败'), 0, 24)) ?>
                            </span>
                        <?php else: ?>
                            <a href="/console/generate/<?= (int)$r['id'] ?>" style="color:#b7a8ff">查看进度 →</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <div class="pagination">
                <?php for ($i = 1; $i <= $pagination['last_page']; $i++): ?>
                <a class="<?= $i === $pagination['page'] ? 'active' : '' ?>" href="<?= $e($pageUrl($i)) ?>"><?= $i ?></a>
                <?php endfor; ?>
            </div>
            <?php else: ?>
            <p class="muted">
                <?php if (($filters['keyword'] ?? '') !== '' || ($filters['status'] ?? '') !== ''): ?>
                没有匹配的记录，<a href="/console/generations" style="color:#7b5cff">清空搜索条件</a>
                <?php else: ?>
                暂无生成记录 · <a href="/console/generate" style="color:#7b5cff">去生成头像</a>
                <?php endif; ?>
            </p>
            <?php endif; ?>
        </div>
    </div>
</section>
<script>
// 缩略图点击 → 轻量预览模态（点空白 / × / Esc 关闭）
(function () {
    var ov = null, prevOverflow = '';
    function close() {
        if (!ov) return;
        document.removeEventListener('keydown', onKey);
        document.body.style.overflow = prevOverflow;
        ov.remove(); ov = null;
    }
    function onKey(e) { if (e.key === 'Escape') close(); }
    document.addEventListener('click', function (e) {
        var t = e.target.closest('.gen-thumb');
        if (!t) return;
        var url = t.getAttribute('data-full');
        if (!url) return;
        close();
        ov = document.createElement('div');
        ov.className = 'gv';
        var img = document.createElement('img');
        img.src = url; img.alt = '生成结果预览';
        var x = document.createElement('button');
        x.type = 'button'; x.className = 'gv-x'; x.textContent = '×'; x.setAttribute('aria-label', '关闭');
        x.addEventListener('click', close);
        ov.appendChild(img); ov.appendChild(x);
        ov.addEventListener('click', function (ev) { if (ev.target === ov) close(); });
        document.body.appendChild(ov);
        prevOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        document.addEventListener('keydown', onKey);
    });
})();
</script>
<script>
window.__genSeries = <?= json_encode($genChartSeries ?: [], JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR) ?>;
// 生成走势图表（与积分记录页同款；指标/范围存 sessionStorage，自动刷新后恢复选择）
(function () {
    var raw = Array.isArray(window.__genSeries) ? window.__genSeries : [];
    var cv = document.getElementById('gcChart');
    var wrap = document.getElementById('gcWrap');
    var tip = document.getElementById('gcTip');
    var summary = document.getElementById('gcSummary');
    var emptyBox = document.getElementById('gcEmpty');
    if (!cv) return;

    var SS_KEY = 'gc_chart_state';
    var METRICS = {
        total:   { label: '生成数',   color: '#8b73ff', type: 'line' },
        success: { label: '成功',     color: '#6fff9a', type: 'bar' },
        failed:  { label: '失败',     color: '#ff8a8a', type: 'bar' },
        points:  { label: '消耗积分', color: '#00d4ff', type: 'bar' }
    };
    var state = { metric: 'total', range: 30, hover: -1 };
    // 恢复上次选择
    try {
        var saved = JSON.parse(sessionStorage.getItem(SS_KEY) || 'null');
        if (saved && METRICS[saved.metric] && saved.range >= 0) {
            state.metric = saved.metric;
            state.range = parseInt(saved.range, 10) || 0;
        }
    } catch (e) { /* 忽略 */ }
    function saveState() {
        try { sessionStorage.setItem(SS_KEY, JSON.stringify({ metric: state.metric, range: state.range })); } catch (e) { /* 忽略 */ }
    }

    var PAD = { l: 48, r: 14, t: 16, b: 26 };

    function sliceData() {
        return state.range > 0 ? raw.slice(-state.range) : raw;
    }
    function niceMax(v) {
        if (v <= 0) return 1;
        var p = Math.pow(10, Math.floor(Math.log10(v)));
        var n = v / p;
        return (n <= 1 ? 1 : n <= 2 ? 2 : n <= 5 ? 5 : 10) * p;
    }
    function hexA(hex, a) {
        var r = parseInt(hex.slice(1, 3), 16), g = parseInt(hex.slice(3, 5), 16), b = parseInt(hex.slice(5, 7), 16);
        return 'rgba(' + r + ',' + g + ',' + b + ',' + a + ')';
    }

    function draw() {
        var dpr = window.devicePixelRatio || 1;
        var w = wrap.clientWidth, h = 240;
        cv.width = w * dpr; cv.height = h * dpr;
        var ctx = cv.getContext('2d');
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        ctx.clearRect(0, 0, w, h);
        if (!raw.length) { emptyBox.hidden = false; cv.style.display = 'none'; return; }

        var data = sliceData();
        var m = METRICS[state.metric];
        var plotW = w - PAD.l - PAD.r, plotH = h - PAD.t - PAD.b;
        var n = data.length;
        var step = plotW / Math.max(n, 1);
        var vals = data.map(function (d) { return d[state.metric]; });
        var vmax = niceMax(Math.max.apply(null, vals.concat([0])));
        var vmin = 0;
        function y(v) { return PAD.t + plotH - (v - vmin) / (vmax - vmin) * plotH; }
        function x(i) { return PAD.l + (i + 0.5) * step; }

        // 网格 + Y 轴刻度
        ctx.font = '11px -apple-system,system-ui,sans-serif';
        ctx.textAlign = 'right'; ctx.textBaseline = 'middle';
        var ticks = 4;
        for (var t = 0; t <= ticks; t++) {
            var v = vmin + (vmax - vmin) * t / ticks;
            var yy = y(v);
            ctx.strokeStyle = 'rgba(255,255,255,.08)';
            ctx.lineWidth = 1;
            ctx.beginPath(); ctx.moveTo(PAD.l, yy); ctx.lineTo(w - PAD.r, yy); ctx.stroke();
            ctx.fillStyle = 'rgba(255,255,255,.5)';
            ctx.fillText(String(Math.round(v)), PAD.l - 8, yy);
        }

        // X 轴日期标签（稀疏）
        ctx.textAlign = 'center'; ctx.textBaseline = 'top';
        var tickN = Math.min(6, n);
        for (var i = 0; i < tickN; i++) {
            var idx = tickN === 1 ? Math.floor(n / 2) : Math.round(i * (n - 1) / (tickN - 1));
            ctx.fillStyle = 'rgba(255,255,255,.5)';
            ctx.fillText(data[idx].date.slice(5), x(idx), h - PAD.b + 8);
        }

        // 悬停参考线
        if (state.hover >= 0 && state.hover < n) {
            ctx.strokeStyle = 'rgba(139,115,255,.45)';
            ctx.setLineDash([4, 4]);
            ctx.beginPath(); ctx.moveTo(x(state.hover), PAD.t); ctx.lineTo(x(state.hover), h - PAD.b); ctx.stroke();
            ctx.setLineDash([]);
        }

        if (m.type === 'bar') {
            var bw = Math.max(2, Math.min(18, step * 0.62));
            for (var j = 0; j < n; j++) {
                var v = vals[j];
                if (v <= 0 && j !== state.hover) continue;
                var y0 = y(0), y1 = y(v);
                var top = Math.min(y0, y1), hgt = Math.max(Math.abs(y0 - y1), 1);
                var g = ctx.createLinearGradient(0, top, 0, top + hgt);
                g.addColorStop(0, m.color);
                g.addColorStop(1, hexA(m.color, 0.35));
                ctx.fillStyle = j === state.hover ? '#fff' : g;
                ctx.fillRect(x(j) - bw / 2, top, bw, hgt);
            }
        } else {
            var lg = ctx.createLinearGradient(0, PAD.t, 0, PAD.t + plotH);
            lg.addColorStop(0, hexA(m.color, 0.35));
            lg.addColorStop(1, hexA(m.color, 0.02));
            ctx.beginPath();
            for (var k = 0; k < n; k++) {
                if (k === 0) ctx.moveTo(x(k), y(vals[k])); else ctx.lineTo(x(k), y(vals[k]));
            }
            var linePath = new Path2D();
            for (var k2 = 0; k2 < n; k2++) {
                if (k2 === 0) linePath.moveTo(x(k2), y(vals[k2])); else linePath.lineTo(x(k2), y(vals[k2]));
            }
            ctx.lineTo(x(n - 1), y(vmin));
            ctx.lineTo(x(0), y(vmin));
            ctx.closePath();
            ctx.fillStyle = lg;
            ctx.fill();
            ctx.strokeStyle = m.color;
            ctx.lineWidth = 2;
            ctx.lineJoin = 'round';
            ctx.stroke(linePath);
            if (n <= 40) {
                for (var p = 0; p < n; p++) {
                    ctx.beginPath();
                    ctx.arc(x(p), y(vals[p]), p === state.hover ? 4.5 : 2.5, 0, Math.PI * 2);
                    ctx.fillStyle = p === state.hover ? '#fff' : m.color;
                    ctx.fill();
                }
            } else if (state.hover >= 0) {
                ctx.beginPath();
                ctx.arc(x(state.hover), y(vals[state.hover]), 4.5, 0, Math.PI * 2);
                ctx.fillStyle = '#fff';
                ctx.fill();
            }
        }
    }

    function renderSummary() {
        if (!raw.length) { summary.textContent = ''; return; }
        var d = sliceData();
        var total = 0, ok = 0, fail = 0, pts = 0;
        d.forEach(function (r) { total += r.total; ok += r.success; fail += r.failed; pts += r.points; });
        var rate = total > 0 ? Math.round(ok / total * 100) : 0;
        summary.innerHTML = '期间生成 <b>' + total + '</b> 次 · 成功 <b style="color:#6fff9a">' + ok +
            '</b> · 失败 <b style="color:#ff8a8a">' + fail + '</b> · 成功率 <b>' + rate +
            '%</b> · 消耗 <b style="color:#00d4ff">' + pts + '</b> 积分';
    }

    function showTip(i, mouseX) {
        var d = sliceData()[i];
        if (!d) { tip.hidden = true; return; }
        var m = METRICS[state.metric];
        tip.innerHTML = '<b>' + d.date + '</b><br>' + m.label + '：<b style="color:' + m.color + '">' + d[state.metric] + '</b>';
        tip.hidden = false;
        var tw = tip.offsetWidth;
        var lx = Math.min(Math.max(mouseX - tw / 2, 4), wrap.clientWidth - tw - 4);
        tip.style.left = lx + 'px';
        tip.style.top = '8px';
    }

    function idxFromEvent(e) {
        var rect = cv.getBoundingClientRect();
        var mx = e.clientX - rect.left;
        var data = sliceData();
        var step = (rect.width - PAD.l - PAD.r) / Math.max(data.length, 1);
        var i = Math.floor((mx - PAD.l) / step);
        return (i >= 0 && i < data.length) ? i : -1;
    }

    var active = false;
    cv.addEventListener('pointerdown', function (e) { active = true; onMove(e); });
    cv.addEventListener('pointermove', onMove);
    cv.addEventListener('pointerup', function () { active = false; });
    cv.addEventListener('pointerleave', function () {
        if (!active) { state.hover = -1; tip.hidden = true; draw(); }
        active = false;
    });
    function onMove(e) {
        if (!raw.length) return;
        var i = idxFromEvent(e);
        if (i !== state.hover) {
            state.hover = i;
            draw();
        }
        if (i >= 0) showTip(i, e.clientX - cv.getBoundingClientRect().left);
        else tip.hidden = true;
        if (active && e.pointerType === 'touch') e.preventDefault();
    }

    function syncChips() {
        document.querySelectorAll('#gcMetrics .pt-chip').forEach(function (x) {
            x.classList.toggle('active', x.getAttribute('data-m') === state.metric);
        });
        document.querySelectorAll('#gcRanges .pt-chip').forEach(function (x) {
            x.classList.toggle('active', parseInt(x.getAttribute('data-r'), 10) === state.range);
        });
    }

    document.getElementById('gcMetrics').addEventListener('click', function (e) {
        var b = e.target.closest('.pt-chip');
        if (!b) return;
        state.metric = b.getAttribute('data-m');
        state.hover = -1;
        saveState(); syncChips(); draw();
    });
    document.getElementById('gcRanges').addEventListener('click', function (e) {
        var b = e.target.closest('.pt-chip');
        if (!b) return;
        state.range = parseInt(b.getAttribute('data-r'), 10) || 0;
        state.hover = -1;
        saveState(); syncChips(); draw(); renderSummary();
    });

    var rzTimer = null;
    window.addEventListener('resize', function () {
        clearTimeout(rzTimer);
        rzTimer = setTimeout(function () { state.hover = -1; tip.hidden = true; draw(); }, 150);
    });

    syncChips();
    renderSummary();
    draw();
})();
</script>
<?php if ($hasActive): ?>
<script>
// 有进行中任务时每 5 秒自动刷新（输入框聚焦时延迟，避免打断搜索输入）
function genAutoRefresh() {
    setTimeout(function () {
        var el = document.activeElement;
        if (!el || (el.tagName !== 'INPUT' && el.tagName !== 'SELECT' && el.tagName !== 'TEXTAREA')) {
            location.reload();
        } else {
            genAutoRefresh();
        }
    }, 5000);
}
genAutoRefresh();
</script>
<?php endif; ?>
<?php include __DIR__ . '/../_partials/footer.php'; ?>
