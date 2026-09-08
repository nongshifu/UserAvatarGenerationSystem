<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Log;

/**
 * 豆包图生图 API 封装
 * 文档：https://ark.cn-beijing.volces.com/api/v3/images/generations
 */
final class ArkApiService
{
    /**
     * 调用图生图
     * @return array{request_id:string,result_url:string,raw:array}
     * @throws \RuntimeException
     */
    public static function generateImage(string $originImageUrl, string $prompt): array
    {
        $apiKey  = (string)Config::get('api', 'ark_api_key');
        $baseUrl = (string)Config::get('api', 'ark_base_url', 'https://ark.cn-beijing.volces.com/api/v3');
        $model   = (string)Config::get('api', 'ark_model', 'doubao-seedream-5-0-260128');
        // size 豆包要求小写：2k / 3k / 4k 或 宽x高（如 1024x1024），统一转小写避免 '2K' 被判非法
        $size    = strtolower(trim((string)Config::get('api', 'image_size', '2k'))) ?: '2k';
        $watermark = (bool)Config::get('api', 'watermark', true);

        if ($apiKey === '') {
            throw new \RuntimeException('ARK API Key not configured', 5001);
        }

        // 豆包服务器需公网可访问的完整 URL：站内相对路径（/uploads/...）补全为绝对地址
        if (!preg_match('#^https?://#i', $originImageUrl)) {
            $originImageUrl = SeoService::absoluteUrl($originImageUrl);
        }
        Log::info('ark origin absolute url', ['url' => $originImageUrl]);

        $body = [
            'model'                       => $model,
            'prompt'                      => $prompt,
            'image'                       => $originImageUrl,
            'sequential_image_generation' => 'disabled',
            'response_format'             => 'url',
            'size'                        => $size,
            'stream'                      => false,
            'watermark'                   => $watermark,
        ];

        $ch = curl_init($baseUrl . '/images/generations');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        $resp   = (string)curl_exec($ch);
        $errno  = curl_errno($ch);
        $info   = curl_getinfo($ch);
        curl_close($ch);

        if ($errno !== 0) {
            Log::error('ark curl error', ['errno' => $errno, 'msg' => curl_strerror($errno)]);
            throw new \RuntimeException('ARK request failed: ' . curl_strerror($errno), 5001);
        }

        $decoded = json_decode($resp, true);
        if (!is_array($decoded)) {
            Log::error('ark invalid json', ['body' => $resp]);
            throw new \RuntimeException('ARK invalid response', 5001);
        }

        // 豆包成功：data[0].url
        $resultUrl = $decoded['data'][0]['url'] ?? '';
        $requestId  = $decoded['id'] ?? ('http_' . $info['http_code']);

        if ($resultUrl === '') {
            Log::error('ark no image url', ['resp' => $decoded]);
            throw new \RuntimeException('ARK no image url in response', 5001);
        }

        return [
            'request_id'  => $requestId,
            'result_url'  => $resultUrl,
            'raw'         => $decoded,
        ];
    }
}
