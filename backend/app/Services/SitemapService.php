<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

/**
 * sitemap.xml 生成
 * - 首页 + 分类页 + 头像详情页
 * - 头像详情较多时，按游标分页（每 5000 条一个 sitemap）
 */
final class SitemapService
{
    /**
     * 生成 sitemap.xml 内容
     */
    public static function render(): string
    {
        $base = SeoService::siteBaseUrl();
        $urls = [];

        // 首页
        $urls[] = ['loc' => $base . '/', 'priority' => '1.0', 'changefreq' => 'daily'];
        // 分类页
        $urls[] = ['loc' => $base . '/category', 'priority' => '0.9', 'changefreq' => 'daily'];
        $styles = DB::table('styles')->where('status', 1)->orderBy('id', 'ASC')->all();
        foreach ($styles as $s) {
            $urls[] = ['loc' => $base . '/category/' . (int)$s['id'], 'priority' => '0.8', 'changefreq' => 'daily'];
        }
        // 公共头像详情
        $avatars = DB::table('avatars')
            ->select('id', 'created_at')
            ->where('is_public', 1)
            ->where('audit_status', 'approved')
            ->where('status', 'success')
            ->orderBy('id', 'DESC')
            ->limit(5000)
            ->all();
        foreach ($avatars as $av) {
            $lastmod = substr((string)$av['created_at'], 0, 10);
            $urls[] = ['loc' => $base . '/avatar/' . (int)$av['id'], 'priority' => '0.6', 'changefreq' => 'weekly', 'lastmod' => $lastmod];
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $u) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>' . htmlspecialchars($u['loc'], ENT_XML1) . "</loc>\n";
            if (!empty($u['lastmod'])) {
                $xml .= '    <lastmod>' . $u['lastmod'] . "</lastmod>\n";
            }
            $xml .= '    <changefreq>' . $u['changefreq'] . "</changefreq>\n";
            $xml .= '    <priority>' . $u['priority'] . "</priority>\n";
            $xml .= "  </url>\n";
        }
        $xml .= '</urlset>';
        return $xml;
    }

    /**
     * robots.txt
     */
    public static function robots(): string
    {
        $base = SeoService::siteBaseUrl();
        return "User-agent: *\nAllow: /\nDisallow: /admin/\nDisallow: /console/\nDisallow: /api/\n\nSitemap: {$base}/sitemap.xml\n";
    }
}
