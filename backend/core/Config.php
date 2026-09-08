<?php
declare(strict_types=1);

namespace App\Core;

/**
 * 配置加载器
 * 支持 PHP 文件配置（config/{name}.php）+ DB 配置（site_settings 表，运行时合并）
 * 用法：Config::get('api', 'ark_model', 'default')
 */
final class Config
{
    /** @var array<string,mixed> 已加载的文件配置 */
    private static array $files = [];

    /** @var array<string,mixed> 已加载的 DB 配置（按 group 缓存） */
    private static array $dbGroups = [];

    /**
     * 读取配置
     * 优先级：DB 配置（site_settings） > 文件配置（config/{group}.php） > 默认值
     */
    public static function get(string $group, string $key, mixed $default = null): mixed
    {
        $dbValue = self::getFromDb($group, $key);
        if ($dbValue !== null) {
            return $dbValue;
        }
        $fileConfig = self::loadFile($group);
        return $fileConfig[$key] ?? $default;
    }

    /**
     * 读取整组配置（DB 合并 文件）
     * @return array<string,mixed>
     */
    public static function group(string $group): array
    {
        $fileConfig = self::loadFile($group);
        $dbConfig   = self::loadDbGroup($group);
        return array_merge($fileConfig, $dbConfig);
    }

    /**
     * 设置运行时配置（仅内存，不持久化）
     */
    public static function set(string $group, string $key, mixed $value): void
    {
        self::$files[$group][$key] = $value;
    }

    private static function loadFile(string $group): array
    {
        if (isset(self::$files[$group]) && array_key_exists($group, self::$files)) {
            return self::$files[$group];
        }
        $path = ROOT_PATH . '/config/' . $group . '.php';
        if (is_file($path)) {
            self::$files[$group] = require $path;
        } else {
            self::$files[$group] = [];
        }
        return self::$files[$group];
    }

    /**
     * 从 DB 缓存读取一组配置
     * @return array<string,mixed>
     */
    private static function loadDbGroup(string $group): array
    {
        if (array_key_exists($group, self::$dbGroups)) {
            return self::$dbGroups[$group];
        }
        try {
            $rows = DB::table('site_settings')->where('group_key', $group)->all();
            $out  = [];
            foreach ($rows as $row) {
                $out[$row['item_key']] = self::castValue($row['item_value'], $row['item_type'] ?? 'string');
            }
            self::$dbGroups[$group] = $out;
            return $out;
        } catch (\Throwable) {
            // 表未建或 DB 未连接时，静默返回空数组
            self::$dbGroups[$group] = [];
            return [];
        }
    }

    private static function getFromDb(string $group, string $key): mixed
    {
        $groupConfig = self::loadDbGroup($group);
        return array_key_exists($key, $groupConfig) ? $groupConfig[$key] : null;
    }

    /**
     * 强制刷新一组配置的 DB 缓存
     */
    public static function refreshGroup(string $group): void
    {
        unset(self::$dbGroups[$group]);
    }

    private static function castValue(string $value, string $type): mixed
    {
        return match ($type) {
            'number'  => is_numeric($value) ? (strpos($value, '.') !== false ? (float)$value : (int)$value) : $value,
            'boolean' => $value === 'true' || $value === '1' || $value === 1,
            'json'    => json_decode($value, true) ?: [],
            default   => $value,
        };
    }
}
