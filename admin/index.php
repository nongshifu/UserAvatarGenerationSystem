<?php
/**
 * 管理后台入口
 *
 * 资源缓存开关（调试用）：
 *   $ASSET_DEBUG = true  → 每次请求给 css/js 加当前时间戳，浏览器永远拿最新文件
 *   $ASSET_DEBUG = false → 使用固定版本号 $ASSET_VER，浏览器长期缓存（上线用）
 */
$ASSET_DEBUG = true;
$ASSET_VER   = $ASSET_DEBUG ? (string)time() : '1.0.0';
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>头像引擎后台</title>
    <script>
    // 首屏前恢复主题与侧边栏状态，避免闪烁
    (function () {
        var t = localStorage.getItem('avatar_admin_theme') || 'light';
        document.documentElement.setAttribute('data-theme', t);
        if (localStorage.getItem('avatar_admin_sidebar') === '1') {
            document.documentElement.classList.add('sidebar-collapsed');
        }
    })();
    </script>
    <link rel="stylesheet" href="/admin/assets/app.css?v=<?= $ASSET_VER ?>">
</head>
<body>
<div class="sidebar-mask" id="sidebar-mask"></div>
<div class="layout">
    <aside class="sidebar">
        <div class="logo">
            <svg class="logo-mark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="4"/><line x1="12" y1="2" x2="12" y2="6"/><line x1="12" y1="18" x2="12" y2="22"/></svg>
            <span class="logo-text">头像引擎</span>
        </div>
        <nav class="menu" id="menu"></nav>
    </aside>
    <div class="main">
        <header class="header">
            <div class="header-left">
                <button class="icon-btn" id="sidebar-toggle" title="折叠 / 展开菜单"></button>
                <div id="page-title">仪表盘</div>
            </div>
            <div class="user">
                <button class="icon-btn" id="cache-flush-btn" title="刷新站点缓存（价格/设置改后前台不更新时点此）"></button>
                <button class="icon-btn" id="cache-clean-btn" title="清理上传缓存（删除 7 天前的参考原图）"></button>
                <button class="icon-btn" id="theme-toggle" title="切换暗色 / 亮色主题"></button>
                <span id="admin-name"></span>
                <button class="btn btn-sm" id="logout-btn">退出</button>
            </div>
        </header>
        <div class="tabs-bar">
            <div class="tab-bar" id="tab-bar"></div>
        </div>
        <main class="content" id="content"></main>
    </div>
</div>
<script src="/admin/assets/app.js?v=<?= $ASSET_VER ?>"></script>
<script src="/admin/assets/pages.js?v=<?= $ASSET_VER ?>"></script>
<script src="/admin/assets/router.js?v=<?= $ASSET_VER ?>"></script>
</body>
</html>
