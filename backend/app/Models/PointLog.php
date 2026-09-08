<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class PointLog extends Model
{
    protected string $table = 'point_logs';
    protected string $primaryKey = 'id';
    protected array $fillable = ['user_id', 'type', 'change', 'balance', 'related_id', 'remark'];
    // point_logs 不维护 updated_at（只增不改）
    protected bool $timestamps = false;

    /**
     * 写一条流水（不动 users.points，由调用方保证一致）
     */
    public static function record(int $userId, string $type, int $change, int $balanceAfter, ?int $relatedId = null, string $remark = ''): void
    {
        static::create([
            'user_id'     => $userId,
            'type'        => $type,
            'change'      => $change,
            'balance'     => $balanceAfter,
            'related_id'  => $relatedId,
            'remark'      => $remark,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
    }
}
