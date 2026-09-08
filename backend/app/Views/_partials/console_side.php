<?php
/** @var string $active 控制台菜单高亮键 */
$active = $active ?? '';
$uid = $currentUser ? (int)$currentUser->id : 0;
$uname = (string)($currentUser->username ?? 'U');
$avatarLetter = mb_substr($uname, 0, 1, 'UTF-8');
$sideIcon = function (string $name): string {
    $paths = [
        'home'     => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>',
        'generate' => '<path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z"/>',
        'avatars'  => '<circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/>',
        'records'  => '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>',
        'key'      => '<path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/>',
        'recharge' => '<rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/>',
        'points'   => '<line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>',
        'orders'   => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>',
        'profile'  => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'logout'   => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
    ];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . ($paths[$name] ?? '') . '</svg>';
};
?>
<aside class="console-side">
    <div class="cs-user">
        <div class="cs-avatar"><?= $e($avatarLetter) ?></div>
        <div style="min-width:0">
            <div class="cs-name"><?= $e($uname) ?></div>
            <div class="cs-points">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                余额 <?= $e((string)($balance ?? 0)) ?> 积分
            </div>
        </div>
    </div>
    <button type="button" class="cs-menu-toggle" id="csMenuToggle" aria-expanded="false" aria-controls="csMenu">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
        <span>功能菜单</span>
        <svg class="cs-caret" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
    </button>
    <nav class="cs-menu" id="csMenu">
        <a class="item <?= $active==='index'?'active':'' ?>" href="/console"><?= $sideIcon('home') ?><span>概览</span></a>
        <a class="item <?= $active==='generate'?'active':'' ?>" href="/console/generate"><?= $sideIcon('generate') ?><span>生成头像</span></a>
        <a class="item <?= $active==='avatars'?'active':'' ?>" href="/console/avatars"><?= $sideIcon('avatars') ?><span>我的头像</span></a>
        <a class="item <?= $active==='generations'?'active':'' ?>" href="/console/generations"><?= $sideIcon('records') ?><span>生成记录</span></a>
        <a class="item <?= $active==='keys'?'active':'' ?>" href="/console/keys"><?= $sideIcon('key') ?><span>API KEY</span></a>
        <a class="item <?= $active==='recharge'?'active':'' ?>" href="/console/recharge"><?= $sideIcon('recharge') ?><span>积分充值</span></a>
        <a class="item <?= $active==='points'?'active':'' ?>" href="/console/points"><?= $sideIcon('points') ?><span>积分记录</span></a>
        <a class="item <?= $active==='orders'?'active':'' ?>" href="/console/orders"><?= $sideIcon('orders') ?><span>订单记录</span></a>
        <a class="item <?= $active==='profile'?'active':'' ?>" href="/console/profile"><?= $sideIcon('profile') ?><span>个人资料</span></a>
        <a class="item cs-logout" href="/logout"><?= $sideIcon('logout') ?><span>退出登录</span></a>
    </nav>
</aside>
<script>
// 移动端控制台侧栏折叠：点击「功能菜单」展开/收起（桌面端按钮隐藏，菜单常驻）
(function () {
    var btn = document.getElementById('csMenuToggle');
    var menu = document.getElementById('csMenu');
    if (!btn || !menu) return;
    btn.addEventListener('click', function () {
        var open = menu.classList.toggle('open');
        btn.classList.toggle('is-open', open);
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
})();
</script>
