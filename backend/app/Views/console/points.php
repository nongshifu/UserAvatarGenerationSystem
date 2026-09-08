<?php
$active = 'points';
include __DIR__ . '/../_partials/header.php';

/** @var array $list */
/** @var array $pagination */
/** @var array $filters */

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
<section class="container">
    <div class="console-layout">
        <?php include __DIR__ . '/../_partials/console_side.php'; ?>
        <div>
            <h2 style="margin-bottom:16px">积分记录</h2>

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
<?php include __DIR__ . '/../_partials/footer.php'; ?>
