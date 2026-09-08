<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Request;
use App\Models\AuditLog;

/**
 * 审计日志服务
 */
final class AuditLogService
{
    /**
     * 记录管理员操作
     */
    public static function record(Request $req, int $adminId, string $action, string $targetType, int $targetId, string $detail = ''): void
    {
        AuditLog::record(
            $adminId,
            $action,
            $targetType,
            $targetId,
            $detail,
            $req->ip(),
            $req->userAgent()
        );
    }
}
