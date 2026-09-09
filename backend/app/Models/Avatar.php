<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use App\Core\Model;
use App\Core\RedisClient;
use App\Core\Log;
use App\Services\ObjectStorageService;

class Avatar extends Model
{
    protected string $table = 'avatars';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'user_id', 'key_id', 'sub_user_id', 'origin_url', 'result_url',
        'result_thumb_url', 'prompt_id', 'style_id', 'color_id', 'shape_id',
        'prompt_text', 'is_public', 'audit_status', 'status',
        'width', 'height', 'file_size', 'views',
    ];

    /**
     * 从公共池随机抽 N 个头像
     * @param array{style_id?:int,color_id?:int,shape_id?:int} $filter
     */
    public static function randomPublic(int $count = 1, array $filter = []): array
    {
        $cacheKey = 'avatar:random:' . md5(json_encode($filter) . '|' . $count);
        $cached   = RedisClient::get($cacheKey);
        if ($cached) {
            $decoded = json_decode($cached, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $q = static::query()
            ->where('is_public', 1)
            ->where('audit_status', 'approved')
            ->where('status', 'success');
        if (!empty($filter['style_id'])) {
            $q->where('style_id', (int)$filter['style_id']);
        }
        if (!empty($filter['color_id'])) {
            $q->where('color_id', (int)$filter['color_id']);
        }
        if (!empty($filter['shape_id'])) {
            $q->where('shape_id', (int)$filter['shape_id']);
        }

        // MySQL 5.6 ORDER BY RAND() 在大表上慢，先取候选 ID 池再抽样
        $rows = DB::table('avatars')
            ->select('id', 'result_url', 'result_thumb_url', 'style_id', 'color_id', 'shape_id')
            ->where('is_public', 1)
            ->where('audit_status', 'approved')
            ->where('status', 'success')
            ->orderBy('id', 'DESC')
            ->limit(500)
            ->all();

        shuffle($rows);
        $picked = array_slice($rows, 0, $count);
        RedisClient::set($cacheKey, $picked, 600); // 10min
        // 记录此 filter 签名，供生成完成后批量清缓存
        $sig = md5(json_encode($filter) . '|' . $count);
        RedisClient::sAdd('avatar:random:sigs', $sig);
        return $picked;
    }

    /**
     * 浏览量 +1（详情页用）
     */
    public function incrementViews(): void
    {
        if (!$this->exists) {
            return;
        }
        DB::table($this->table)
            ->where($this->primaryKey, $this->getKey())
            ->update(['views' => ((int)$this->attributes['views']) + 1]);
    }

    /**
     * 删除头像：先删 DB 记录，再清理物理文件（原图/结果图/缩略图）
     * 文件清理失败不影响 DB 删除（打日志即可，避免脏数据残留）
     */
    public function deleteWithFiles(): bool
    {
        // 注意：$exists 是基类 private 属性，子类不能直接访问（会触发 __get 返回 null），
        // 必须通过基类暴露的 isExists() 方法判断
        if (!$this->isExists()) {
            return false;
        }
        $urls = [
            (string)($this->attributes['origin_url'] ?? ''),
            (string)($this->attributes['result_url'] ?? ''),
            (string)($this->attributes['result_thumb_url'] ?? ''),
        ];
        // 先删库，再删文件（防止 DB 删除失败但文件已删的不一致）
        $deleted = $this->delete();
        foreach ($urls as $u) {
            $u = trim($u);
            if ($u !== '') {
                try {
                    ObjectStorageService::delete($u);
                } catch (\Throwable $e) {
                    Log::warning('avatar file delete failed', ['url' => $u, 'err' => $e->getMessage()]);
                }
            }
        }
        return $deleted;
    }
}
