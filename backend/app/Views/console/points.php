<?php
$active = 'points';
include __DIR__ . '/../_partials/header.php';

/** @var array $list */
/** @var array $pagination */
/** @var array $filters */
/** @var array $chartSeries */

$typeMap = [
    'register'     => '注册赠送',
    'recharge'     => '充值',
    'consume'      => '生成消费',
    'refund'       => '退款',
    'admin_add'    => '管理员调整',
    'admin_sub'    => '管理员扣除',
    'audit_refund' => '审核退回',
];

// 分页链接（保留搜索条件）
$pageUrl = function (int $p) use ($filters): string {
    $q = array_filter($filters, fn($v) => $v !== '' && $v !== null);
    $q['page'] = $p;
    return '?' . http_build_query($q);
};
$hasFilter = ($filters['keyword'] ?? '') !== '' || ($filters['type'] ?? '') !== '';
?>
<style>
/* 积分走势图表 */
.pt-chips{display:flex;gap:6px;flex-wrap:wrap}
.pt-chip{display:inline-flex;align-items:center;gap:6px;padding:4px 12px;border-radius:14px;border:1px solid rgba(255,255,255,.18);background:rgba(255,255,255,.05);color:rgba(255,255,255,.72);font-size:.78rem;cursor:pointer;transition:all .15s}
.pt-chip i{width:8px;height:8px;border-radius:50%;display:inline-block}
.pt-chip:hover{border-color:rgba(139,115,255,.6);color:#fff}
.pt-chip.active{background:linear-gradient(90deg,rgba(123,92,255,.35),rgba(0,212,255,.3));border-color:rgba(139,115,255,.8);color:#fff;font-weight:600}
.pt-summary{font-size:.78rem;margin-bottom:6px}
.pt-summary b{color:#fff}
.pt-tip{position:absolute;pointer-events:none;background:rgba(10,14,30,.95);border:1px solid rgba(139,115,255,.5);border-radius:8px;padding:7px 10px;font-size:.75rem;color:#fff;box-shadow:0 6px 20px rgba(0,0,0,.4);white-space:nowrap;z-index:5}
</style>
<section class="container">
    <div class="console-layout">
        <?php include __DIR__ . '/../_partials/console_side.php'; ?>
        <div>
            <h2 style="margin-bottom:16px">积分记录</h2>

            <!-- 积分走势图表 -->
            <div class="card" style="margin-bottom:18px">
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:10px">
                    <span style="font-weight:600">积分走势</span>
                    <div class="pt-chips" id="ptMetrics">
                        <button type="button" class="pt-chip active" data-m="balance"><i style="background:#00d4ff"></i>余额</button>
                        <button type="button" class="pt-chip" data-m="income"><i style="background:#6fff9a"></i>收入</button>
                        <button type="button" class="pt-chip" data-m="expense"><i style="background:#ff8a8a"></i>支出</button>
                        <button type="button" class="pt-chip" data-m="net"><i style="background:#8b73ff"></i>净变动</button>
                    </div>
                    <div style="flex:1"></div>
                    <div class="pt-chips" id="ptRanges">
                        <button type="button" class="pt-chip" data-r="7">近7天</button>
                        <button type="button" class="pt-chip active" data-r="30">近30天</button>
                        <button type="button" class="pt-chip" data-r="90">近90天</button>
                        <button type="button" class="pt-chip" data-r="0">全部</button>
                    </div>
                </div>
                <div class="pt-summary muted" id="ptSummary"></div>
                <div style="position:relative" id="ptWrap">
                    <canvas id="ptChart" style="width:100%;height:240px;display:block;touch-action:pan-y"></canvas>
                    <div class="pt-tip" id="ptTip" hidden></div>
                </div>
                <div class="muted" id="ptEmpty" hidden style="text-align:center;padding:30px 0">暂无积分数据</div>
            </div>

            <!-- 搜索栏 -->
            <form method="get" action="/console/points" style="display:flex;gap:10px;align-items:center;margin-bottom:18px;flex-wrap:wrap">
                <input type="text" name="q" value="<?= $e((string)($filters['keyword'] ?? '')) ?>" placeholder="搜索备注关键词" style="flex:1;min-width:200px;max-width:320px">
                <div style="width:150px">
                    <select name="type">
                        <option value="">全部类型</option>
                        <?php foreach ($typeMap as $tv => $tl): ?>
                        <option value="<?= $tv ?>" <?= ($filters['type'] ?? '') === $tv ? 'selected' : '' ?>><?= $tl ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn" style="margin:0">搜索</button>
                <?php if ($hasFilter): ?>
                <a class="btn btn-outline" href="/console/points" style="margin:0">重置</a>
                <?php endif; ?>
            </form>

            <?php if (!empty($list)): ?>
            <table class="table">
                <thead><tr><th>时间</th><th>类型</th><th>变动</th><th>余额</th><th>备注</th></tr></thead>
                <tbody>
                <?php foreach ($list as $r): $ch = (int)$r['change']; ?>
                <tr>
                    <td class="muted" style="white-space:nowrap"><?= $e((string)$r['created_at']) ?></td>
                    <td><?= $e($typeMap[$r['type']] ?? (string)$r['type']) ?></td>
                    <td style="color:<?= $ch > 0 ? '#6fff9a' : ($ch < 0 ? '#ff8a8a' : '#aaa') ?>;font-weight:600"><?= ($ch > 0 ? '+' : '') . $ch ?></td>
                    <td><?= $e((string)$r['balance']) ?></td>
                    <td class="muted"><?= $e((string)$r['remark']) ?></td>
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
                <?php if ($hasFilter): ?>
                没有匹配的记录，<a href="/console/points" style="color:#7b5cff">清空搜索条件</a>
                <?php else: ?>
                暂无积分记录
                <?php endif; ?>
            </p>
            <?php endif; ?>
        </div>
    </div>
</section>
<script>
window.__pointSeries = <?= json_encode($chartSeries ?: [], JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR) ?>;
(function () {
    var raw = Array.isArray(window.__pointSeries) ? window.__pointSeries : [];
    var cv = document.getElementById('ptChart');
    var wrap = document.getElementById('ptWrap');
    var tip = document.getElementById('ptTip');
    var summary = document.getElementById('ptSummary');
    var emptyBox = document.getElementById('ptEmpty');
    if (!cv) return;

    var METRICS = {
        balance: { label: '余额',   color: '#00d4ff', type: 'line' },
        income:  { label: '收入',   color: '#6fff9a', type: 'bar' },
        expense: { label: '支出',   color: '#ff8a8a', type: 'bar' },
        net:     { label: '净变动', color: '#8b73ff', type: 'bar' }
    };
    var state = { metric: 'balance', range: 30, hover: -1 };
    var PAD = { l: 48, r: 14, t: 16, b: 26 };

    function sliceData() {
        return state.range > 0 ? raw.slice(-state.range) : raw;
    }
    function fmt(n) { return (n > 0 ? '+' : '') + n; }
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

        var posMax = niceMax(Math.max.apply(null, vals.concat([0])));
        var negMax = state.metric === 'net' ? niceMax(Math.max.apply(null, vals.map(function (v) { return -v; }).concat([0]))) : 0;
        var vmax = posMax, vmin = -negMax;
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
        // 零轴线（净变动可能为负）
        if (vmin < 0) {
            ctx.strokeStyle = 'rgba(255,255,255,.22)';
            ctx.beginPath(); ctx.moveTo(PAD.l, y(0)); ctx.lineTo(w - PAD.r, y(0)); ctx.stroke();
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
                var color = state.metric === 'net' ? (v >= 0 ? '#6fff9a' : '#ff8a8a') : m.color;
                var y0 = y(0), y1 = y(v);
                var top = Math.min(y0, y1), hgt = Math.max(Math.abs(y0 - y1), 1);
                var g = ctx.createLinearGradient(0, top, 0, top + hgt);
                g.addColorStop(0, color);
                g.addColorStop(1, hexA(color, 0.35));
                ctx.fillStyle = j === state.hover ? '#fff' : g;
                ctx.fillRect(x(j) - bw / 2, top, bw, hgt);
            }
        } else {
            // 余额：渐变面积 + 曲线
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
        var inc = 0, exp = 0, net = 0;
        d.forEach(function (r) { inc += r.income; exp += r.expense; net += r.net; });
        summary.innerHTML = '期间收入 <b style="color:#6fff9a">+' + inc + '</b> · 支出 <b style="color:#ff8a8a">-' + exp +
            '</b> · 净变动 <b style="color:' + (net >= 0 ? '#6fff9a' : '#ff8a8a') + '">' + fmt(net) +
            '</b> · 期末余额 <b style="color:#00d4ff">' + d[d.length - 1].balance + '</b> 积分';
    }

    function showTip(i, mouseX) {
        var d = sliceData()[i];
        if (!d) { tip.hidden = true; return; }
        var m = METRICS[state.metric];
        tip.innerHTML = '<b>' + d.date + '</b><br>' + m.label + '：<b style="color:' +
            (state.metric === 'net' ? (d.net >= 0 ? '#6fff9a' : '#ff8a8a') : m.color) + '">' +
            (state.metric === 'balance' ? d.balance : fmt(d[state.metric])) + '</b> 积分';
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

    // 控件切换
    document.getElementById('ptMetrics').addEventListener('click', function (e) {
        var b = e.target.closest('.pt-chip');
        if (!b) return;
        this.querySelectorAll('.pt-chip').forEach(function (x) { x.classList.toggle('active', x === b); });
        state.metric = b.getAttribute('data-m');
        state.hover = -1;
        draw();
    });
    document.getElementById('ptRanges').addEventListener('click', function (e) {
        var b = e.target.closest('.pt-chip');
        if (!b) return;
        this.querySelectorAll('.pt-chip').forEach(function (x) { x.classList.toggle('active', x === b); });
        state.range = parseInt(b.getAttribute('data-r'), 10);
        state.hover = -1;
        draw();
        renderSummary();
    });

    var rzTimer = null;
    window.addEventListener('resize', function () {
        clearTimeout(rzTimer);
        rzTimer = setTimeout(function () { state.hover = -1; tip.hidden = true; draw(); }, 150);
    });

    renderSummary();
    draw();
})();
</script>
<?php include __DIR__ . '/../_partials/footer.php'; ?>
