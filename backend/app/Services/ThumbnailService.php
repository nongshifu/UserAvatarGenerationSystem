<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Log;

/**
 * 缩略图生成（GD，无需第三方库）
 * - 输入：已转存的结果图 resultUrl + 其 storage targetPath
 * - 输出：缩略图公网 URL
 *
 * 本地存储模式下，原图在 ROOT_PATH/../storage/uploads/{targetPath}
 * 缩略图存为同目录下 {base}_thumb.{ext}
 */
final class ThumbnailService
{
    private const MAX_WIDTH = 400;

    /**
     * 生成缩略图，返回公网 URL
     * 若 GD 不可用或源图不存在，回退为原图 URL
     */
    public static function make(string $resultUrl, string $targetPath): string
    {
        if (!function_exists('imagecreatetruecolor')) {
            return $resultUrl; // GD 不可用，回退
        }
        $localPath = self::localPathOf($targetPath);
        if (!is_file($localPath)) {
            return $resultUrl;
        }
        $info = @getimagesize($localPath);
        if (!$info) {
            return $resultUrl;
        }
        $mime = $info['mime'] ?? '';
        $src = self::createSource($localPath, $mime);
        if (!$src) {
            return $resultUrl;
        }
        $w = imagesx($src);
        $h = imagesy($src);
        if ($w <= 0 || $h <= 0) {
            if (is_resource($src) || $src instanceof \GdImage) {
                imagedestroy($src);
            }
            return $resultUrl;
        }
        $newW = min(self::MAX_WIDTH, $w);
        $newH = (int)($h * ($newW / $w));
        $thumb = imagecreatetruecolor($newW, $newH);
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
        imagefill($thumb, 0, 0, imagecolorallocatealpha($thumb, 0, 0, 0, 127));
        imagecopyresampled($thumb, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);

        $ext = strtolower(pathinfo($targetPath, PATHINFO_EXTENSION)) ?: 'png';
        $thumbTarget = self::thumbTargetPath($targetPath, $ext);
        $thumbLocal = self::localPathOf($thumbTarget);
        $thumbDir = dirname($thumbLocal);
        if (!is_dir($thumbDir)) {
            @mkdir($thumbDir, 0777, true);
        }
        $saved = match ($ext) {
            'jpg', 'jpeg' => imagejpeg($thumb, $thumbLocal, 85),
            'png'         => imagepng($thumb, $thumbLocal, 6),
            'webp'        => imagewebp($thumb, $thumbLocal, 85),
            default       => imagepng($thumb, $thumbLocal, 6),
        };
        imagedestroy($src);
        imagedestroy($thumb);
        if (!$saved) {
            return $resultUrl;
        }
        $base = (string)Config::get('storage', 'public_base', '/uploads');
        return $base . '/' . ltrim($thumbTarget, '/');
    }

    private static function localPathOf(string $targetPath): string
    {
        return ROOT_PATH . '/../storage/uploads/' . ltrim($targetPath, '/');
    }

    private static function thumbTargetPath(string $targetPath, string $ext): string
    {
        $dir = dirname($targetPath);
        $name = pathinfo($targetPath, PATHINFO_FILENAME);
        return $dir . '/' . $name . '_thumb.' . $ext;
    }

    /** @return \GdImage|resource|null */
    private static function createSource(string $path, string $mime): mixed
    {
        try {
            return match ($mime) {
                'image/jpeg'        => @imagecreatefromjpeg($path) ?: null,
                'image/png'         => @imagecreatefrompng($path) ?: null,
                'image/webp'         => (function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null) ?: null,
                default             => null,
            };
        } catch (\Throwable $e) {
            Log::error('thumbnail source create failed', ['err' => $e->getMessage(), 'mime' => $mime]);
            return null;
        }
    }
}
