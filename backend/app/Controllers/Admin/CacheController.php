<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Log;
use App\Core\RedisClient;
use App\Core\Request;
use App\Core\Response;
use App\Services\UserService;

/**
 * 缓存维护
 * - POST /admin-api/cache/flush         刷新站点缓存（维度/随机头像池等）
 * - POST /admin-api/cache/clean-uploads 清理过期的上传原图（src_/up_ 前缀，不碰生成结果）
 */
final class CacheController
{
    /**
     * 刷新站点缓存
     */
    public function flush(Request $req, Response $res, array $params): void
    {
        $redis = RedisClient::client();
        $deleted = 0;

        // 1. 风格/颜色/形状维度缓存（价格、提示词、后缀变更后需刷新）
        $deleted += (int)$redis->del(UserService::DIMS_CACHE_KEY);

        // 2. 公共池随机头像缓存（头像上下架/审核后需刷新）
        $sigs = RedisClient::sMembers('avatar:random:sigs');
        foreach ($sigs as $sig) {
            $deleted += (int)$redis->del('avatar:random:' . $sig);
        }
        $deleted += (int)$redis->del('avatar:random:sigs');

        // 3. 站点设置无独立缓存（Config 每次读库），无需处理

        $res->json(['flushed' => true, 'deleted_keys' => $deleted]);
    }

    /**
     * 清理上传的参考原图
     * 仅删除 storage/uploads/月份目录/ 下 src_*、up_* 文件（avatars/ 目录为生成结果，不碰）
     */
    public function cleanUploads(Request $req, Response $res, array $params): void
    {
        $days = (int)$req->input('days', 7);
        if ($days < 1) {
            $days = 7;
        }
        $cutoff = time() - $days * 86400;
        $base = ROOT_PATH . '/../storage/uploads';

        $deleted = 0;
        $freedBytes = 0;
        $scanned = 0;

        if (is_dir($base)) {
            foreach (glob($base . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
                // avatars 目录存的是生成结果与缩略图，绝不清理
                if (basename($dir) === 'avatars') {
                    continue;
                }
                foreach (glob($dir . '/{src_,up_}*', GLOB_BRACE) ?: [] as $file) {
                    if (!is_file($file)) {
                        continue;
                    }
                    $scanned++;
                    $mtime = @filemtime($file);
                    if ($mtime === false || $mtime >= $cutoff) {
                        continue;
                    }
                    $size = (int)@filesize($file);
                    if (@unlink($file)) {
                        $deleted++;
                        $freedBytes += $size;
                    }
                }
            }
        }

        Log::info('clean uploads', ['days' => $days, 'deleted' => $deleted, 'freed' => $freedBytes]);
        $res->json([
            'cleaned'     => true,
            'days'        => $days,
            'deleted'     => $deleted,
            'scanned'     => $scanned,
            'freed_mb'    => round($freedBytes / 1024 / 1024, 2),
        ]);
    }
}
