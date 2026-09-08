<?php
declare(strict_types=1);

namespace App\Services;

/**
 * 官网用户生成入口（无 KEY 模式）
 * 复用 AvatarGenerateService::createTask，传 keyId=null / subUserIdentifier=null
 * 生成的头像仍入公共池（受审核开关控制）
 */
final class WebGenerateService
{
    /**
     * 创建生成任务
     * @return array{record_id:int,cost_points:int,remaining_points:int}
     */
    public static function create(int $userId, string $originImageUrl, array $params): array
    {
        return AvatarGenerateService::createTask(
            $userId,
            null,           // 无 KEY
            null,           // 无子用户
            $originImageUrl,
            $params
        );
    }
}
