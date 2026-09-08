<?php
/** @var string $title $keywords $description $siteName $ogImage */
/** @var \App\Models\User|null $currentUser */
$cu = $currentUser ?? null;
$nav = $nav ?? '';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $e($title) ?></title>
    <?php if (!empty($keywords)): ?><meta name="keywords" content="<?= $e($keywords) ?>"><?php endif; ?>
    <?php if (!empty($description)): ?><meta name="description" content="<?= $e($description) ?>"><?php endif; ?>
    <meta property="og:title" content="<?= $e($title) ?>">
    <meta property="og:description" content="<?= $e($description ?? '') ?>">
    <?php if (!empty($ogImage)): ?><meta property="og:image" content="<?= $e($ogImage) ?>"><?php endif; ?>
    <meta property="og:type" content="website">
    <link rel="canonical" href="<?= $e($siteBaseUrl ?? '/') ?>">
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","PingFang SC","Hiragino Sans GB","Microsoft YaHei",sans-serif;background:linear-gradient(135deg,#1a1a2e 0%,#16213e 50%,#0f3460 100%);color:#fff;min-height:100vh}
        a{color:#fff;text-decoration:none}
        .container{max-width:1200px;margin:0 auto;padding:0 20px}
        /* ── 顶部导航（全站共用） ── */
        .site-nav{position:sticky;top:0;z-index:100;background:rgba(15,22,41,.62);backdrop-filter:blur(18px) saturate(1.5);-webkit-backdrop-filter:blur(18px) saturate(1.5);border-bottom:1px solid rgba(255,255,255,.09)}
        .nav-inner{max-width:1280px;margin:0 auto;padding:0 24px;height:68px;display:flex;align-items:center;justify-content:space-between;gap:16px}
        .nav-brand{display:flex;align-items:center;gap:11px;font-weight:800;font-size:1.22rem;white-space:nowrap}
        .nav-brand:hover{text-decoration:none}
        .brand-mark{width:38px;height:38px;border-radius:11px;background:linear-gradient(135deg,#7b5cff,#00d4ff);display:flex;align-items:center;justify-content:center;box-shadow:0 6px 18px rgba(123,92,255,.5);flex-shrink:0}
        .brand-mark svg{width:21px;height:21px;color:#fff}
        .brand-text{background:linear-gradient(90deg,#a78bff,#5ee2ff);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent}
        .nav-links{display:flex;align-items:center;gap:4px}
        /* 导航项统一高度 38px、inline-flex 居中；透明边框避免 active 态加边框时跳动 */
        .nav-links a{display:inline-flex;align-items:center;height:38px;padding:0 16px;border-radius:20px;border:1px solid transparent;font-size:.9rem;color:rgba(255,255,255,.72);transition:all .15s;white-space:nowrap}
        .nav-links a:hover{color:#fff;background:rgba(255,255,255,.09);text-decoration:none}
        .nav-links a.active{color:#fff;background:linear-gradient(90deg,rgba(123,92,255,.42),rgba(0,212,255,.28));border-color:rgba(139,115,255,.5);font-weight:600}
        .nav-auth{display:flex;align-items:center;gap:10px;white-space:nowrap}
        /* 右侧按钮组统一 38px 高，与导航链接对齐 */
        .nav-auth .nav-chip{display:inline-flex;align-items:center;gap:6px;height:38px;padding:0 14px;border-radius:20px;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.12);font-size:.88rem;color:#ffd98a;margin:0}
        .nav-chip svg{width:15px;height:15px}
        .nav-chip:hover{text-decoration:none;background:rgba(255,255,255,.12)}
        .nav-auth .nav-cta{display:inline-flex;align-items:center;height:38px;padding:0 20px;border-radius:20px;font-size:.9rem;font-weight:600;margin:0}
        .nav-auth .nav-login{display:inline-flex;align-items:center;height:38px;padding:0 18px;border-radius:20px;font-size:.9rem;color:rgba(255,255,255,.85);border:1px solid rgba(255,255,255,.25);margin:0}
        .nav-login:hover{text-decoration:none;background:rgba(255,255,255,.08);color:#fff}
        .nav-auth .nav-logout{display:inline-flex;align-items:center;height:38px;padding:0 12px;border-radius:20px;border:1px solid transparent;font-size:.88rem;color:rgba(255,255,255,.6);margin:0}
        .nav-logout:hover{color:#ff9c9c;text-decoration:none;background:rgba(255,80,80,.1)}
        .nav-burger{display:none;width:40px;height:40px;padding:0;border:none;background:rgba(255,255,255,.07);border-radius:10px;align-items:center;justify-content:center}
        .nav-burger svg{width:22px;height:22px;color:#fff}
        .mobile-only{display:none}
        /* .nav-links a 优先级(0,1,1)高于 .mobile-only(0,1,0)，需显式压回，避免移动端专属链接在桌面显示 */
        .nav-links a.mobile-only{display:none}
        @media(max-width:860px){
            .nav-inner{padding:0 16px;height:60px}
            .nav-burger{display:inline-flex}
            .nav-links{display:none;position:absolute;top:100%;left:0;right:0;flex-direction:column;align-items:stretch;gap:2px;padding:12px 16px 16px;background:rgba(13,20,36,.97);border-bottom:1px solid rgba(255,255,255,.1)}
            .nav-links.open{display:flex}
            .nav-links a{height:auto;padding:13px 16px;border-radius:10px;font-size:1rem}
            .nav-links a.mobile-only{display:block}
            .nav-auth .desk-only{display:none}
        }
        .btn{display:inline-block;padding:10px 24px;border-radius:30px;background:linear-gradient(90deg,#7b5cff,#00d4ff);color:#fff;text-decoration:none;font-weight:600;margin:6px;transition:transform .2s,box-shadow .2s}
        .btn:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(123,92,255,.4)}
        .btn-outline{background:transparent;border:1px solid rgba(255,255,255,.3)}
        .btn-sm{padding:6px 14px;font-size:.85rem}
        /* 找回密码渠道切换按钮 */
        .channel-btn{margin:0;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.16);color:#fff;border-radius:12px!important;font-weight:500;cursor:pointer;transition:all .15s}
        .channel-btn:hover{border-color:rgba(123,92,255,.5);transform:none;box-shadow:none}
        .channel-btn.active{background:linear-gradient(90deg,#7b5cff,#00d4ff);border-color:transparent;box-shadow:0 4px 14px rgba(123,92,255,.35)}
        .card{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.08);border-radius:14px;padding:20px}
        .section{padding:40px 0}
        .section h2{font-size:1.6rem;margin-bottom:20px;text-align:center}
        .muted{opacity:.6}
        .grid{display:grid;gap:16px}
        .avatar-grid{grid-template-columns:repeat(auto-fill,minmax(150px,1fr))}
        .avatar-card{aspect-ratio:1;border-radius:12px;overflow:hidden;background:rgba(255,255,255,.05);transition:transform .3s}
        .avatar-card:hover{transform:scale(1.03)}
        .avatar-card img{width:100%;height:100%;object-fit:cover;display:block}
        /* sticky footer：内容不足一屏时吸附视口底部，超出时自然跟在内容后，不影响其他布局 */
        footer{position:sticky;top:100vh;text-align:center;padding:30px 20px;opacity:.6;font-size:.85rem}
        input,select,textarea{width:100%;padding:10px 12px;border-radius:8px;border:1px solid rgba(255,255,255,.15);background:rgba(255,255,255,.06);color:#fff;font-size:.95rem}
        input:focus,select:focus,textarea:focus{outline:none;border-color:#7b5cff}
        label{display:block;margin:10px 0 4px;font-size:.9rem;opacity:.85}
        .form-card{max-width:420px;margin:0 auto}
        .row{display:flex;gap:10px;flex-wrap:wrap}
        .badge{display:inline-block;padding:2px 10px;border-radius:12px;font-size:.75rem;background:rgba(123,92,255,.2);border:1px solid rgba(123,92,255,.4)}
        .badge-ok{background:rgba(40,200,120,.18);border-color:rgba(40,200,120,.4)}
        .badge-off{background:rgba(255,80,80,.15);border-color:rgba(255,80,80,.35)}
        .alert{padding:12px 16px;border-radius:10px;margin-bottom:16px;background:rgba(255,80,80,.12);border:1px solid rgba(255,80,80,.35);color:#ffb3b3}
        .table{width:100%;border-collapse:collapse}
        .table th,.table td{padding:10px;text-align:left;border-bottom:1px solid rgba(255,255,255,.08);font-size:.9rem}
        .table th{opacity:.6;font-weight:500}
        .tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px}
        .tabs a{padding:6px 14px;border-radius:20px;font-size:.9rem;background:rgba(255,255,255,.06)}
        .tabs a.active{background:linear-gradient(90deg,#7b5cff,#00d4ff)}
        .pagination{display:flex;gap:6px;justify-content:center;margin-top:20px}
        .pagination a{padding:6px 12px;border-radius:8px;background:rgba(255,255,255,.06);font-size:.85rem}
        .pagination a.active{background:#7b5cff}
        .console-layout{display:grid;grid-template-columns:236px 1fr;gap:26px;padding:34px 0;align-items:start}
        .console-side{position:sticky;top:92px}
        .cs-user{background:linear-gradient(135deg,rgba(123,92,255,.24),rgba(0,212,255,.12));border:1px solid rgba(139,115,255,.38);border-radius:16px;padding:16px;display:flex;align-items:center;gap:12px;margin-bottom:14px}
        .cs-avatar{width:46px;height:46px;border-radius:50%;background:linear-gradient(135deg,#7b5cff,#00d4ff);display:flex;align-items:center;justify-content:center;font-weight:800;font-size:1.15rem;color:#fff;flex-shrink:0;box-shadow:0 4px 14px rgba(123,92,255,.4)}
        .cs-name{font-weight:700;font-size:1rem;line-height:1.3;word-break:break-all}
        .cs-points{margin-top:3px;font-size:.82rem;color:#ffd98a;display:inline-flex;align-items:center;gap:5px}
        .cs-points svg{width:13px;height:13px}
        .console-side .item{display:flex;align-items:center;gap:11px;padding:11px 14px;border-radius:11px;margin-bottom:4px;color:rgba(255,255,255,.72);font-size:.94rem;transition:all .15s;white-space:nowrap}
        .console-side .item svg{width:17px;height:17px;flex-shrink:0;opacity:.8}
        .console-side .item:hover{background:rgba(255,255,255,.07);color:#fff;text-decoration:none}
        .console-side .item.active{background:linear-gradient(90deg,rgba(123,92,255,.42),rgba(0,212,255,.26));border:1px solid rgba(139,115,255,.5);color:#fff;font-weight:600}
        .console-side .item.active svg{opacity:1}
        /* 控制台侧栏：移动端折叠为抽屉（点击「功能菜单」展开，类似顶部汉堡） */
        .cs-menu-toggle{display:none;width:100%;align-items:center;gap:9px;height:44px;padding:0 16px;border-radius:12px;border:1px solid rgba(139,115,255,.5);background:linear-gradient(90deg,rgba(123,92,255,.35),rgba(0,212,255,.22));color:#fff;font-size:.95rem;font-weight:600;font-family:inherit;cursor:pointer;margin:0 0 12px;transition:filter .15s}
        .cs-menu-toggle:hover{filter:brightness(1.12)}
        .cs-menu-toggle svg{width:18px;height:18px;flex-shrink:0}
        .cs-menu-toggle .cs-caret{margin-left:auto;opacity:.75;transition:transform .2s}
        .cs-menu-toggle.is-open .cs-caret{transform:rotate(180deg)}
        /* 侧栏底部「退出登录」仅移动端展开时出现 */
        .console-side a.cs-logout{display:none;color:rgba(255,156,156,.85)}
        .console-side a.cs-logout:hover{background:rgba(255,80,80,.12);color:#ff9c9c}

        /* ── 表单控件美化（自定义下拉 / 单选胶囊 / 输入框） ── */
        input,select,textarea{transition:border-color .15s,box-shadow .15s,background .15s}
        input:focus,textarea:focus{outline:none;border-color:#8b73ff;box-shadow:0 0 0 3px rgba(123,92,255,.22)}
        input::placeholder,textarea::placeholder{color:rgba(255,255,255,.35)}
        /* 自定义下拉（JS 自动把 <select> 增强为 .ui-select，原生 select 隐藏但保留提交） */
        .ui-select{position:relative;display:block;width:100%;z-index:20}
        /* 打开时整体抬高层级，确保菜单盖住下方其他表单控件/卡片 */
        .ui-select.is-open{z-index:1000}
        .ui-select-trigger{display:flex;align-items:center;justify-content:space-between;gap:8px;width:100%;padding:10px 14px;border-radius:10px;border:1px solid rgba(255,255,255,.16);background:rgba(255,255,255,.07);color:#fff;font-size:.94rem;line-height:1.4;cursor:pointer;text-align:left;font-family:inherit;transition:border-color .15s,box-shadow .15s,background .15s}
        .ui-select-trigger:hover{background:rgba(255,255,255,.11);border-color:rgba(255,255,255,.3)}
        .ui-select.is-open .ui-select-trigger{border-color:#8b73ff;box-shadow:0 0 0 3px rgba(123,92,255,.25);background:rgba(255,255,255,.1)}
        .ui-select-value{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        .ui-select-caret{width:16px;height:16px;flex-shrink:0;opacity:.65;transition:transform .2s}
        .ui-select.is-open .ui-select-caret{transform:rotate(180deg);opacity:1}
        .ui-select-menu{position:absolute;left:0;right:0;top:calc(100% + 6px);max-height:264px;overflow-y:auto;background:rgba(20,28,50,.98);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,.15);border-radius:12px;padding:6px;box-shadow:0 18px 50px rgba(0,0,0,.55);display:none;z-index:1000}
        .ui-select.is-open .ui-select-menu{display:block;animation:ui-pop-in .16s ease}
        .ui-select.drop-up .ui-select-menu{top:auto;bottom:calc(100% + 6px);animation-name:ui-pop-up}
        @keyframes ui-pop-in{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:none}}
        @keyframes ui-pop-up{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}
        .ui-select-option{padding:10px 13px;border-radius:8px;cursor:pointer;font-size:.92rem;color:rgba(255,255,255,.82);display:flex;align-items:center;gap:9px;white-space:nowrap}
        .ui-select-option:hover,.ui-select-option.is-highlighted{background:rgba(123,92,255,.30);color:#fff}
        .ui-select-option.is-selected{background:rgba(123,92,255,.18);color:#fff;font-weight:600}
        .ui-select-option.is-selected::after{content:'✓';margin-left:auto;color:#9d8bff;font-weight:700}
        .ui-select-menu::-webkit-scrollbar{width:6px}
        .ui-select-menu::-webkit-scrollbar-thumb{background:rgba(255,255,255,.16);border-radius:3px}
        .ui-select select{position:absolute;width:1px;height:1px;opacity:0;pointer-events:none}
        /* 单选胶囊组（风格选择） */
        .radio-pills{display:flex;flex-wrap:wrap;gap:10px}
        label.radio-pill{display:inline-flex;align-items:center;gap:7px;padding:9px 18px;border-radius:22px;cursor:pointer;border:1px solid rgba(255,255,255,.16);background:rgba(255,255,255,.06);font-size:.9rem;color:rgba(255,255,255,.78);transition:all .15s;user-select:none}
        label.radio-pill:hover{background:rgba(255,255,255,.12);border-color:rgba(255,255,255,.3);color:#fff}
        label.radio-pill input{position:absolute;width:1px;height:1px;opacity:0;pointer-events:none}
        label.radio-pill:has(input:checked){background:linear-gradient(90deg,rgba(123,92,255,.5),rgba(0,212,255,.32));border-color:rgba(139,115,255,.65);color:#fff;font-weight:600;box-shadow:0 4px 14px rgba(123,92,255,.32)}
        /* 复选项 */
        .check-line{display:flex;align-items:center;gap:9px;cursor:pointer;color:rgba(255,255,255,.85);font-size:.92rem}
        .check-line input{width:17px;height:17px;accent-color:#7b5cff;cursor:pointer;flex-shrink:0}
        /* 图片上传区 */
        .uploader{margin-top:8px;border:1.5px dashed rgba(255,255,255,.25);border-radius:12px;background:rgba(255,255,255,.04);cursor:pointer;transition:border-color .15s,background .15s,box-shadow .15s;overflow:hidden}
        .uploader:hover,.uploader.drag-over{border-color:#8b73ff;background:rgba(123,92,255,.08)}
        .uploader.drag-over{box-shadow:0 0 0 3px rgba(123,92,255,.22)}
        /* display:flex 会覆盖 hidden 属性，显式保证隐藏 */
        .uploader [hidden]{display:none!important}
        .uploader-empty{padding:40px 20px;text-align:center;color:rgba(255,255,255,.72);display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:190px}
        .uploader-empty svg{color:#8b73ff;opacity:.9}
        .uploader-preview{display:flex;align-items:center;gap:18px;padding:18px;min-height:190px;box-sizing:border-box}
        .uploader-preview img{width:110px;height:110px;object-fit:cover;border-radius:12px;border:1px solid rgba(255,255,255,.2);flex-shrink:0;background:rgba(0,0,0,.3);box-shadow:0 6px 18px rgba(0,0,0,.35)}
        .uploader-meta{flex:1;min-width:0;display:flex;flex-direction:column;gap:12px;align-items:flex-start;justify-content:center}
        .uploader-name{font-size:.92rem;color:rgba(255,255,255,.9);word-break:break-all;text-align:left;font-weight:600}
        .uploader-hint{font-size:.8rem;color:rgba(255,255,255,.45)}

        /* ── 首页：大气版（仅首页使用以下类，不影响其他页面） ── */
        .btn-lg{padding:14px 34px;font-size:1rem;border-radius:30px}
        .grad-text{background:linear-gradient(90deg,#a78bff,#5ee2ff);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent}
        /* Hero 区：光斑 + 网格底纹 */
        .hero-wrap{position:relative;overflow:hidden;padding:92px 0 76px}
        .hero-bg{position:absolute;inset:0;z-index:0;overflow:hidden;pointer-events:none}
        .glow{position:absolute;border-radius:50%;filter:blur(90px)}
        .glow-1{width:520px;height:520px;background:#7b5cff;opacity:.32;top:-180px;left:-120px}
        .glow-2{width:460px;height:460px;background:#00d4ff;opacity:.20;top:-110px;right:-140px}
        .glow-3{width:400px;height:400px;background:#b06cff;opacity:.16;bottom:-220px;left:36%}
        .hero-grid{position:absolute;inset:0;background-image:linear-gradient(rgba(255,255,255,.045) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.045) 1px,transparent 1px);background-size:48px 48px;-webkit-mask-image:radial-gradient(ellipse 72% 62% at 50% 32%,#000 25%,transparent 78%);mask-image:radial-gradient(ellipse 72% 62% at 50% 32%,#000 25%,transparent 78%)}
        .hero-inner{position:relative;z-index:1;text-align:center}
        .hero-badge{display:inline-flex;align-items:center;gap:8px;padding:7px 18px;border-radius:30px;background:rgba(123,92,255,.15);border:1px solid rgba(123,92,255,.38);font-size:.85rem;color:#cdc2ff;margin-bottom:26px}
        .hero-badge svg{width:15px;height:15px}
        .hero-title{font-size:clamp(2.4rem,6vw,4.2rem);line-height:1.16;font-weight:800;letter-spacing:1px;margin-bottom:22px}
        .hero-sub{max-width:620px;margin:0 auto 36px;font-size:1.06rem;line-height:1.9;opacity:.78}
        .hero-actions{display:flex;gap:14px;justify-content:center;flex-wrap:wrap;margin-bottom:66px}
        .hero-stats{display:flex;justify-content:center;gap:clamp(32px,7vw,72px);flex-wrap:wrap}
        .hero-stats .stat-num{font-size:clamp(1.8rem,3.5vw,2.3rem);font-weight:800;line-height:1.2;background:linear-gradient(90deg,#a78bff,#5ee2ff);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent}
        .hero-stats .stat-label{font-size:.9rem;opacity:.62;margin-top:6px}
        /* 区块标题 */
        .section-head{text-align:center;margin-bottom:38px}
        .section-head h2{font-size:clamp(1.6rem,3vw,2.2rem);margin-bottom:10px}
        .section-head p{opacity:.6;font-size:.98rem}
        /* 头像池网格 */
        .avatar-grid{gap:18px}
        .avatar-card{position:relative;border:1px solid rgba(255,255,255,.1);box-shadow:0 8px 24px rgba(0,0,0,.25)}
        .avatar-card:hover{transform:translateY(-6px) scale(1.02);box-shadow:0 18px 42px rgba(123,92,255,.35);border-color:rgba(139,115,255,.55)}
        .empty-tip{text-align:center;padding:56px 20px;border:1px dashed rgba(255,255,255,.16);border-radius:18px;background:rgba(255,255,255,.03)}
        .empty-tip svg{width:54px;height:54px;opacity:.35;margin-bottom:14px}
        .empty-tip p{opacity:.65;margin-bottom:18px}
        /* 特色功能卡 */
        .feature-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:20px}
        .feature-card{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.09);border-radius:18px;padding:32px 28px;transition:transform .25s,box-shadow .25s,border-color .25s,background .25s}
        .feature-card:hover{transform:translateY(-6px);border-color:rgba(139,115,255,.5);box-shadow:0 18px 44px rgba(123,92,255,.22);background:rgba(255,255,255,.075)}
        .feature-icon{width:52px;height:52px;border-radius:14px;display:flex;align-items:center;justify-content:center;margin-bottom:18px;background:linear-gradient(135deg,rgba(123,92,255,.32),rgba(0,212,255,.26));border:1px solid rgba(139,115,255,.4)}
        .feature-icon svg{width:26px;height:26px;color:#bda9ff}
        .feature-card h3{font-size:1.15rem;margin-bottom:10px}
        .feature-card p{opacity:.65;font-size:.93rem;line-height:1.8}
        /* 三步流程 */
        .steps{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:20px}
        .step{text-align:center;padding:38px 24px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:18px;transition:transform .25s,border-color .25s}
        .step:hover{transform:translateY(-4px);border-color:rgba(0,212,255,.4)}
        .step-num{width:48px;height:48px;margin:0 auto 18px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.25rem;font-weight:800;background:linear-gradient(135deg,#7b5cff,#00d4ff);box-shadow:0 6px 18px rgba(123,92,255,.45)}
        .step h3{margin-bottom:8px;font-size:1.08rem}
        .step p{opacity:.62;font-size:.9rem;line-height:1.7}
        /* 底部 CTA */
        .cta-wrap{padding:26px 0 84px}
        .cta-box{position:relative;overflow:hidden;text-align:center;padding:64px 30px;border-radius:24px;background:linear-gradient(135deg,rgba(123,92,255,.28),rgba(0,212,255,.16));border:1px solid rgba(139,115,255,.4)}
        .cta-box::before{content:'';position:absolute;width:340px;height:340px;border-radius:50%;background:#7b5cff;opacity:.18;filter:blur(80px);top:-160px;right:-80px}
        .cta-box h2{font-size:clamp(1.5rem,3vw,2.1rem);margin-bottom:12px;position:relative}
        .cta-box p{opacity:.78;margin-bottom:30px;position:relative}
        .cta-box .btn{position:relative}

        @media(max-width:768px){
            .console-layout{grid-template-columns:1fr;padding:20px 0;gap:14px}
            .console-side{position:static}
            /* 移动端：只显示用户卡 +「功能菜单」按钮，菜单默认折叠 */
            .cs-menu-toggle{display:flex}
            .cs-menu{display:none}
            .cs-menu.open{display:block;animation:cs-slide .18s ease}
            .console-side a.cs-logout{display:flex;margin-top:10px}
            @keyframes cs-slide{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:none}}
            .avatar-grid{grid-template-columns:repeat(auto-fill,minmax(120px,1fr))}
            .hero-wrap{padding:60px 0 50px}
            .hero-actions{margin-bottom:46px}
            .feature-card,.step{padding:26px 20px}
        }
    </style>
</head>
<body>
<?php
// 当前路径用于导航高亮
$reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$navActive = function (string $p) use ($reqPath): bool {
    return $p === '/' ? $reqPath === '/' : str_starts_with($reqPath, $p);
};
$cuPoints = $cu ? (int)($cu->points ?? 0) : 0;
?>
<header class="site-nav">
    <div class="nav-inner">
        <a href="/" class="nav-brand">
            <span class="brand-mark">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="4"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/></svg>
            </span>
            <span class="brand-text"><?= $e($siteName) ?></span>
        </a>
        <nav class="nav-links" id="navLinks">
            <a href="/" class="<?= $navActive('/') && !$navActive('/category') && !$navActive('/docs') && !$navActive('/console') ? 'active' : '' ?>">首页</a>
            <a href="/category" class="<?= $navActive('/category') ? 'active' : '' ?>">分类</a>
            <a href="/docs" class="<?= $navActive('/docs') ? 'active' : '' ?>">API</a>
            <?php if ($cu): ?>
                <a href="/console" class="mobile-only <?= $navActive('/console') ? 'active' : '' ?>">控制台</a>
                <a href="/logout" class="mobile-only">退出登录</a>
            <?php else: ?>
                <a href="/login" class="mobile-only">登录</a>
                <a href="/register" class="mobile-only">免费注册</a>
            <?php endif; ?>
        </nav>
        <div class="nav-auth">
            <?php if ($cu): ?>
                <a href="/console/recharge" class="nav-chip desk-only" title="积分余额，点击充值">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                    <?= number_format($cuPoints) ?> 积分
                </a>
                <a href="/console" class="btn btn-sm nav-cta desk-only">控制台</a>
                <a href="/logout" class="nav-logout desk-only">退出</a>
            <?php else: ?>
                <a href="/login" class="nav-login desk-only">登录</a>
                <a href="/register" class="btn btn-sm nav-cta desk-only">免费注册</a>
            <?php endif; ?>
            <button class="nav-burger" id="navBurger" aria-label="打开菜单" type="button">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>
        </div>
    </div>
</header>
<script>
// 移动端汉堡菜单：展开 / 收起导航面板
(function () {
    var burger = document.getElementById('navBurger');
    var links = document.getElementById('navLinks');
    if (!burger || !links) return;
    burger.addEventListener('click', function () { links.classList.toggle('open'); });
    links.addEventListener('click', function (e) { if (e.target.tagName === 'A') links.classList.remove('open'); });
})();
</script>
<script>
// 自定义下拉：自动增强页面上所有 <select>
// - 原生 select 隐藏但保留在表单中，提交值与 onchange 行为完全不变
// - 点选项后给 select 赋值并派发 change 事件（兼容模板里的 onchange="this.form.submit()"）
// - 空间不足时自动向上弹出；点击外部 / Esc 关闭；点 label 也能展开
(function () {
    var CARET = '<svg class="ui-select-caret" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>';

    function closeAll(except) {
        document.querySelectorAll('.ui-select.is-open').forEach(function (w) {
            if (w !== except) { w.classList.remove('is-open'); w.classList.remove('drop-up'); }
        });
    }

    function enhance(sel) {
        if (sel.dataset.uiSelect === '1') return;
        sel.dataset.uiSelect = '1';

        var wrap = document.createElement('div');
        wrap.className = 'ui-select';
        sel.parentNode.insertBefore(wrap, sel);
        wrap.appendChild(sel);

        var trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'ui-select-trigger';
        trigger.setAttribute('aria-haspopup', 'listbox');
        trigger.innerHTML = '<span class="ui-select-value"></span>' + CARET;
        wrap.appendChild(trigger);

        var menu = document.createElement('div');
        menu.className = 'ui-select-menu';
        wrap.appendChild(menu);

        function opts() { return Array.prototype.slice.call(sel.options); }

        function render() {
            trigger.querySelector('.ui-select-value').textContent =
                sel.options[sel.selectedIndex] ? sel.options[sel.selectedIndex].textContent : '';
            menu.innerHTML = '';
            opts().forEach(function (o, i) {
                var item = document.createElement('div');
                item.className = 'ui-select-option' + (i === sel.selectedIndex ? ' is-selected' : '');
                item.textContent = o.textContent;
                item.addEventListener('click', function (e) {
                    e.stopPropagation();
                    if (sel.selectedIndex !== i) {
                        sel.selectedIndex = i;
                        sel.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                    close();
                });
                menu.appendChild(item);
            });
        }

        function open() {
            render();
            closeAll(wrap);
            wrap.classList.add('is-open');
            // 下方空间不足且上方充足时，向上弹出
            var rect = trigger.getBoundingClientRect();
            var needH = Math.min(menu.scrollHeight || 264, 264);
            if (window.innerHeight - rect.bottom < needH + 20 && rect.top > needH + 20) {
                wrap.classList.add('drop-up');
            }
        }
        function close() { wrap.classList.remove('is-open'); wrap.classList.remove('drop-up'); }

        trigger.addEventListener('click', function (e) { e.stopPropagation(); wrap.classList.contains('is-open') ? close() : open(); });
        menu.addEventListener('click', function (e) { e.stopPropagation(); });
        sel.addEventListener('change', render);

        // 仅绑定紧邻的前置 label（模板结构：<label>风格</label><select>），
        // 不能查整个容器——同卡片内有多个 select 时会串绑
        var prev = wrap.previousElementSibling;
        if (prev && prev.tagName === 'LABEL') {
            prev.style.cursor = 'pointer';
            prev.addEventListener('click', function (e) { e.preventDefault(); trigger.click(); });
        }

        render();
    }

    document.addEventListener('click', function () { closeAll(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeAll(); });

    function init() { document.querySelectorAll('select').forEach(enhance); }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
</script>
