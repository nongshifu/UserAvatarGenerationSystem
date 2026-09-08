<?php
$active = 'generations';
include __DIR__ . '/../_partials/header.php';
?>
<style>
@keyframes gen-dot-pulse{0%,100%{opacity:.3}50%{opacity:1}}
.gen-dot{display:inline-block;width:6px;height:6px;border-radius:50%;background:#ffd666;margin-left:7px;vertical-align:middle;animation:gen-dot-pulse 1s infinite}
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
                <thead><tr><th>任务ID</th><th>状态</th><th>风格</th><th>消耗</th><th>时间</th><th>说明 / 操作</th></tr></thead>
                <tbody>
                <?php foreach ($list as $r):
                    $st = (string)$r['status'];
                    $styleName = $styleMap[(int)($r['style_id'] ?? 0)] ?? '默认';
                ?>
                <tr>
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
                        <?php if ($st === 'success' && !empty($r['avatar_id'])): ?>
                            <a href="/avatar/<?= (int)$r['avatar_id'] ?>" style="color:#7fe0ff">查看头像 →</a>
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
