<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Log;

/**
 * 内容安全服务（头像合规判断）
 * - 默认实现：调用阿里云内容安全 / 豆包内容审核（如配置 key）
 * - 未配置 key 时：返回 approved（放行，由后台人工复核）
 *
 * 返回结构：['status'=>'approved'|'rejected', 'reason'=>string]
 */
final class ContentSecurityService
{
    /**
     * 校验图片
     * @param string $imageUrl 公网可访问的图片 URL
     */
    public static function checkImage(string $imageUrl): array
    {
        $apiKey = (string)Config::get('audit', 'security_api_key', '');
        if ($apiKey === '') {
            // 未配置：默认放行
            return ['status' => 'approved', 'reason' => 'no security api key configured'];
        }

        // 预留：调用内容审核 API
        // 这里给出可接入的 curl 模板，实际对接时按厂商文档填充
        try {
            $endpoint = (string)Config::get('audit', 'security_endpoint', '');
            if ($endpoint === '') {
                return ['status' => 'approved', 'reason' => 'no endpoint'];
            }
            $ch = curl_init($endpoint);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $apiKey,
                ],
                CURLOPT_POSTFIELDS     => json_encode(['image' => $imageUrl], JSON_UNESCAPED_UNICODE),
                CURLOPT_TIMEOUT        => 20,
            ]);
            $resp = (string)curl_exec($ch);
            curl_close($ch);
            $decoded = json_decode($resp, true);
            // 约定：返回 { "suggestion": "pass"|"block", "label": "..." }
            $suggestion = $decoded['suggestion'] ?? 'pass';
            if ($suggestion === 'block') {
                return ['status' => 'rejected', 'reason' => $decoded['label'] ?? 'content blocked'];
            }
            return ['status' => 'approved', 'reason' => ''];
        } catch (\Throwable $e) {
            Log::error('content security check error', ['err' => $e->getMessage(), 'url' => $imageUrl]);
            // 审核接口异常：保守放行，由后台人工复核
            return ['status' => 'approved', 'reason' => 'security api error: ' . $e->getMessage()];
        }
    }
}
