<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Models\SiteSetting;

/**
 * 站点设置服务
 * - 读：优先 Redis 缓存，回 DB；写：写 DB + 清缓存
 * - 兼容自写框架的 Config（Config 已带 DB 回退）
 */
final class SiteSettingService
{
    /**
     * 取一组配置
     * @return array<string,mixed>
     */
    public static function getGroup(string $group): array
    {
        return Config::group($group);
    }

    /**
     * 取单个配置
     */
    public static function get(string $group, string $key, mixed $default = null): mixed
    {
        return Config::get($group, $key, $default);
    }

    /**
     * 批量更新一组配置
     * @param array<string,string> $items key→value
     */
    public static function updateGroup(string $group, array $items): void
    {
        foreach ($items as $key => $value) {
            $type = is_numeric($value) ? 'number' : (in_array($value, ['true', 'false'], true) ? 'boolean' : 'string');
            SiteSetting::set($group, (string)$key, (string)$value, $type);
        }
        Config::refreshGroup($group);
    }
}
