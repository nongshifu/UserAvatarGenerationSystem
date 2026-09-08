<?php include __DIR__ . '/../_partials/header.php'; ?>

<!-- ── Hero ── -->
<section class="hero-wrap">
    <div class="hero-bg" aria-hidden="true">
        <span class="glow glow-1"></span>
        <span class="glow glow-2"></span>
        <span class="glow glow-3"></span>
        <div class="hero-grid"></div>
    </div>
    <div class="container hero-inner">
        <div class="hero-badge">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z"/></svg>
            AI 智能生成 · 多风格自由定制
        </div>
        <h1 class="hero-title">一键生成你的<br>专属 <span class="grad-text">AI 头像</span></h1>
        <p class="hero-sub">上传一张真人照片，数秒内生成精美卡通头像。多种艺术风格、配色与形状自由组合，更提供开放 API 支持批量接入。</p>
        <div class="hero-actions">
            <a href="/console/generate" class="btn btn-lg">立即免费生成</a>
            <a href="/docs" class="btn btn-outline btn-lg">查看 API 文档</a>
        </div>
        <div class="hero-stats">
            <div>
                <div class="stat-num"><?= number_format((int)($stats['avatars'] ?? 0)) ?>+</div>
                <div class="stat-label">公共头像作品</div>
            </div>
            <div>
                <div class="stat-num"><?= number_format((int)($stats['users'] ?? 0)) ?>+</div>
                <div class="stat-label">注册用户</div>
            </div>
            <div>
                <div class="stat-num"><?= number_format((int)($stats['styles'] ?? 0)) ?> 种</div>
                <div class="stat-label">艺术风格</div>
            </div>
            <div>
                <div class="stat-num">秒级</div>
                <div class="stat-label">生成耗时</div>
            </div>
        </div>
    </div>
</section>

<!-- ── 公共头像池 ── -->
<section class="section container">
    <div class="section-head">
        <h2>公共头像池</h2>
        <p>用户公开分享的精选作品，点击查看大图与同款风格</p>
    </div>
    <div class="tabs" style="justify-content:center;margin-bottom:26px">
        <a href="/" class="<?= empty($filter['style_id'])?'active':'' ?>">全部</a>
        <?php foreach ($styles as $s): ?>
        <a href="/?style_id=<?= (int)$s['id'] ?>" class="<?= $filter['style_id']===(int)$s['id']?'active':'' ?>"><?= $e($s['name']) ?></a>
        <?php endforeach; ?>
    </div>
    <?php if (!empty($avatars)): ?>
    <div class="grid avatar-grid">
        <?php foreach ($avatars as $av): ?>
        <a class="avatar-card" href="/avatar/<?= (int)$av['id'] ?>">
            <img loading="lazy" src="<?= $e($av['result_thumb_url'] ?: $av['result_url']) ?>" alt="头像 #<?= (int)$av['id'] ?>">
        </a>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="empty-tip">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
        <p>公共池还没有头像作品</p>
        <a href="/console/generate" class="btn">上传照片，生成第一张</a>
    </div>
    <?php endif; ?>
</section>

<!-- ── 核心能力 ── -->
<section class="section container">
    <div class="section-head">
        <h2>核心能力</h2>
        <p>从个人尝鲜到开发者批量接入，一套引擎全部满足</p>
    </div>
    <div class="feature-grid">
        <div class="feature-card">
            <div class="feature-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 3l2.5 5.5L13 11l-5.5 2.5L5 19l-2.5-5.5L-3 11l5.5-2.5z" transform="translate(4 0)"/><path d="M19 3v4M21 5h-4"/></svg>
            </div>
            <h3>一键智能生成</h3>
            <p>上传真人照片，AI 自动完成风格迁移与绘制，几秒输出高清卡通头像，无需任何设计基础。</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
            </div>
            <h3>开放 API 接入</h3>
            <p>开发者凭 KEY 调用，支持 QPS 与日限额管控、子用户体系与积分计费，方便嵌入自有产品。</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>
            </div>
            <h3>公共头像池</h3>
            <p>公开作品自动汇聚成内容池，按风格分类浏览，既为站点持续沉淀内容，也利于搜索收录。</p>
        </div>
    </div>
</section>

<!-- ── 三步流程 ── -->
<section class="section container">
    <div class="section-head">
        <h2>三步生成头像</h2>
        <p>流程极简，新手也能立刻上手</p>
    </div>
    <div class="steps">
        <div class="step">
            <div class="step-num">1</div>
            <h3>上传照片</h3>
            <p>选择一张清晰的正面真人照片，支持常见图片格式</p>
        </div>
        <div class="step">
            <div class="step-num">2</div>
            <h3>挑选风格</h3>
            <p>自由组合艺术风格、主色调与头像形状，也可自定义提示词</p>
        </div>
        <div class="step">
            <div class="step-num">3</div>
            <h3>获取成品</h3>
            <p>等待数秒即可预览、下载高清头像，并可选择公开到头像池</p>
        </div>
    </div>
</section>

<!-- ── 底部 CTA ── -->
<section class="cta-wrap container">
    <div class="cta-box">
        <h2>准备好生成你的专属头像了吗？</h2>
        <p>注册即送积分，无需配置任何环境，打开网页就能开始创作</p>
        <a href="<?= $currentUser ? '/console/generate' : '/register' ?>" class="btn btn-lg"><?= $currentUser ? '立即开始生成' : '免费注册开始' ?></a>
    </div>
</section>

<?php include __DIR__ . '/../_partials/footer.php'; ?>
