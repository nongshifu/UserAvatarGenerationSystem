<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\SeoSetting;
use App\Services\SiteSettingService;

/**
 * SEO 服务
 * - 按 page 取 seo_settings 配置（带变量替换）
 * - 默认 TDK 模板，支持 {siteName} {style} {id} 等占位符
 */
final class SeoService
{
    /**
     * 取某页 TDK
     * @return array{title:string,keywords:string,description:string,og_image:string,canonical:string}
     */
    public static function page(string $page, array $vars = []): array
    {
        $setting = SeoSetting::findByPage($page);
        $siteName = SiteSettingService::get('basic', 'site_name', '头像引擎');
        $vars = array_merge(['siteName' => $siteName, 'sitename' => $siteName], $vars);
        if (!$setting) {
            return [
                'title'       => self::replaceVars($siteName . ' - AI 头像生成引擎', $vars),
                'keywords'    => 'AI头像,卡通头像,头像生成',
                'description' => '上传真人头像，AI 一键生成个性卡通头像，支持多风格/颜色/形状。',
                'og_image'    => '',
                'canonical'   => '',
            ];
        }
        $arr = $setting->toArray();
        return [
            'title'       => self::replaceVars((string)$arr['title'], $vars),
            'keywords'    => self::replaceVars((string)$arr['keywords'], $vars),
            'description' => self::replaceVars((string)$arr['description'], $vars),
            'og_image'    => (string)$arr['og_image'],
            'canonical'   => (string)$arr['canonical'],
        ];
    }

    /**
     * 变量替换：{key} → value
     */
    public static function replaceVars(string $template, array $vars): string
    {
        return preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', static function ($m) use ($vars) {
            return (string)($vars[$m[1]] ?? $m[0]);
        }, $template) ?? $template;
    }

    /**
     * 站点绝对 URL（用于 OG/canonical/sitemap）
     */
    public static function absoluteUrl(string $path): string
    {
        $base = self::siteBaseUrl();
        return rtrim($base, '/') . '/' . ltrim($path, '/');
    }

    public static function siteBaseUrl(): string
    {
        // 注意：DB 里 site_url 可能预置为空字符串，trim 后为空要视为「未配置」继续回退
        $configured = trim((string)SiteSettingService::get('basic', 'site_url', ''));
        if ($configured !== '') {
            return rtrim($configured, '/');
        }
        // 文件配置兜底（config/basic.php），供命令行 Worker 使用
        // 直接读文件：Config::get 会被 DB 里的空字符串占位行挡住，无法回退到文件
        $basicFile = ROOT_PATH . '/config/basic.php';
        if (is_file($basicFile)) {
            $fc = require $basicFile;
            $fileFallback = trim((string)($fc['site_url'] ?? ''));
            if ($fileFallback !== '') {
                return rtrim($fileFallback, '/');
            }
        }
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $scheme . '://' . $host;
    }
}
