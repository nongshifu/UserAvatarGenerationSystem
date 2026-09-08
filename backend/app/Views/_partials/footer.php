<?php
/**
 * 全站底部版权
 * 文案可在后台「系统设置 → 基础设置 → 底部版权」修改
 * 支持占位符：{year} 当前年份，{site} 站点名称
 * ICP 备案号在「基础设置 → ICP 备案号」填写，保存后点顶栏「刷新缓存」即生效
 */
$siteName = $siteName ?? \App\Services\SiteSettingService::get('basic', 'site_name', '头像引擎');
$footerText = (string)\App\Services\SiteSettingService::get('basic', 'footer_text', '');
if (trim($footerText) === '') {
    $footerText = '© {year} {site} · 本系统由原生 PHP + 自写框架驱动';
}
$footerText = strtr($footerText, [
    '{year}' => date('Y'),
    '{site}' => (string)$siteName,
]);
$icp = trim((string)\App\Services\SiteSettingService::get('basic', 'icp', ''));
$analyticsCode = (string)\App\Services\SiteSettingService::get('basic', 'analytics_code', '');
?>
<footer>
    <div><?= htmlspecialchars($footerText, ENT_QUOTES, 'UTF-8') ?></div>
    <?php if ($icp !== ''): ?>
    <div style="margin-top:6px">
        <a href="https://beian.miit.gov.cn/" target="_blank" rel="noopener noreferrer"
           style="color:inherit;text-decoration:none;opacity:.75"><?= htmlspecialchars($icp, ENT_QUOTES, 'UTF-8') ?></a>
    </div>
    <?php endif; ?>
</footer>
<?php if ($analyticsCode !== ''): ?>
<?= $analyticsCode ?>
<?php endif; ?>
</body>
</html>
