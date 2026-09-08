<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class AuditLog extends Model
{
    protected string $table = 'audit_logs';
    protected string $primaryKey = 'id';
    protected array $fillable = ['admin_id', 'action', 'target_type', 'target_id', 'ip', 'user_agent', 'detail'];
    protected bool $timestamps = false;

    /**
     * 写一条审计日志
     */
    public static function record(int $adminId, string $action, string $targetType, int $targetId, string $detail = '', string $ip = '', string $ua = ''): void
    {
        static::create([
            'admin_id'    => $adminId,
            'action'      => $action,
            'target_type' => $targetType,
            'target_id'   => $targetId,
            'ip'          => $ip,
            'user_agent'  => $ua,
            'detail'      => $detail,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
    }
}
