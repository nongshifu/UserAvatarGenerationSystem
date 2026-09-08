<?php
$active = 'orders';
include __DIR__ . '/../_partials/header.php';

/** @var array $list */
/** @var array $pagination */
/** @var array $filters */

// 分页链接（保留搜索条件）
$pageUrl = function (int $p) use ($filters): string {
    $q = array_filter($filters, fn($v) => $v !== '' && $v !== null);
    $q['page'] = $p;
    return '?' . http_build_query($q);
};
$hasFilter = ($filters['keyword'] ?? '') !== '' || ($filters['status'] ?? '') !== '';
?>
<section class="container">
    <div class="console-layout">
        <?php include __DIR__ . '/../_partials/console_side.php'; ?>
        <div>
            <h2 style="margin-bottom:16px">订单记录</h2>

            <!-- 搜索栏 -->
            <form method="get" action="/console/orders" style="display:flex;gap:10px;align-items:center;margin-bottom:18px;flex-wrap:wrap">
                <input type="text" name="q" value="<?= $e((string)($filters['keyword'] ?? '')) ?>" placeholder="订单号或商品名称" style="flex:1;min-width:200px;max-width:320px">
                <div style="width:150px">
                    <select name="status">
                        <option value="">全部状态</option>
                        <?php foreach (['pending' => '待支付', 'paid' => '已支付', 'refunded' => '已退款', 'closed' => '已关闭'] as $sv => $sl): ?>
                        <option value="<?= $sv ?>" <?= ($filters['status'] ?? '') === $sv ? 'selected' : '' ?>><?= $sl ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn" style="margin:0">搜索</button>
                <?php if ($hasFilter): ?>
                <a class="btn btn-outline" href="/console/orders" style="margin:0">重置</a>
                <?php endif; ?>
            </form>

            <?php if (!empty($list)): ?>
            <table class="table">
                <thead><tr><th>订单号</th><th>商品</th><th>金额</th><th>积分</th><th>状态</th><th>时间</th></tr></thead>
                <tbody>
                <?php foreach ($list as $o):
                    $stMap = ['pending' => '待支付', 'paid' => '已支付', 'refunded' => '已退款', 'closed' => '已关闭'];
                    $badgeClass = ['pending' => 'badge-off', 'paid' => 'badge-ok', 'refunded' => 'badge', 'closed' => 'badge'];
                ?>
                <tr>
                    <td style="white-space:nowrap"><?= $e((string)$o['order_no']) ?></td>
                    <td><?= $e((string)$o['product_name']) ?></td>
                    <td>¥<?= $e((string)$o['amount']) ?></td>
                    <td><?= $e((string)$o['points']) ?></td>
                    <td><span class="badge <?= $badgeClass[$o['status']] ?? '' ?>"><?= $e($stMap[$o['status']] ?? $o['status']) ?></span></td>
                    <td class="muted" style="white-space:nowrap"><?= $e((string)$o['created_at']) ?></td>
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
                没有匹配的订单，<a href="/console/orders" style="color:#7b5cff">清空搜索条件</a>
                <?php else: ?>
                暂无订单 · <a href="/console/recharge" style="color:#7b5cff">去充值</a>
                <?php endif; ?>
            </p>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php include __DIR__ . '/../_partials/footer.php'; ?>
