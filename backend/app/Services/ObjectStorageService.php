<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;

/**
 * 对象存储抽象
 * - 当前实现：本地存储（默认）+ 阿里云 OSS（预留）
 * - 接口：upload(string $localPath, string $targetPath) 返回公网 URL
 *
 * 切换：在 site_settings 或 config/storage.php 设置 driver=oss 时，走 OSS 实现
 */
final class ObjectStorageService
{
    /**
     * 上传本地文件到存储，返回公网 URL
     */
    public static function upload(string $localPath, string $targetPath): string
    {
        if (!is_file($localPath)) {
            throw new \RuntimeException('Local file not found: ' . $localPath, 500);
        }
        $driver = (string)Config::get('storage', 'driver', 'local');
        return match ($driver) {
            'oss'     => self::uploadToOss($localPath, $targetPath),
            'qiniu'   => self::uploadToQiniu($localPath, $targetPath),
            'cos'     => self::uploadToCos($localPath, $targetPath),
            default   => self::uploadLocal($localPath, $targetPath),
        };
    }

    /**
     * 从 URL 下载到本地临时路径，再转存到自有存储
     */
    public static function transfer(string $remoteUrl, string $targetPath): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'av_');
        $content = file_get_contents($remoteUrl);
        if ($content === false) {
            throw new \RuntimeException('Download failed: ' . $remoteUrl, 500);
        }
        file_put_contents($tmp, $content);
        $url = self::upload($tmp, $targetPath);
        @unlink($tmp);
        return $url;
    }

    /**
     * 本地存储：保存到 ROOT_PATH/../storage/uploads/{targetPath}
     * 由 Nginx 直接静态映射出 URL
     */
    private static function uploadLocal(string $localPath, string $targetPath): string
    {
        $dir = ROOT_PATH . '/../storage/uploads';
        if (!is_dir($dir) && !@mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new \RuntimeException('Cannot create storage dir', 500);
        }
        $dest = $dir . '/' . ltrim($targetPath, '/');
        $subDir = dirname($dest);
        if (!is_dir($subDir)) {
            mkdir($subDir, 0777, true);
        }
        if (!copy($localPath, $dest)) {
            throw new \RuntimeException('Copy file failed', 500);
        }
        return (string)Config::get('storage', 'public_base', '/uploads') . '/' . ltrim($targetPath, '/');
    }

    // ── 以下为云存储预留实现（实际对接时填充签名/SDK 调用） ──

    private static function uploadToOss(string $localPath, string $targetPath): string
    {
        // TODO: 阿里云 OSS PutObject（手写签名或 SDK）
        throw new \RuntimeException('OSS driver not implemented yet', 500);
    }

    private static function uploadToQiniu(string $localPath, string $targetPath): string
    {
        throw new \RuntimeException('Qiniu driver not implemented yet', 500);
    }

    private static function uploadToCos(string $localPath, string $targetPath): string
    {
        throw new \RuntimeException('COS driver not implemented yet', 500);
    }
}
