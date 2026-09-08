<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Log;

/**
 * 上传图片 AI 预审（豆包图像理解 /responses）
 *
 * 在图生图之前先判断用户上传的图片是否合规、是否适合作为头像，
 * 避免任意图片（违规图/风景/截图/二维码等）进入生成流程浪费积分与模型调用。
 *
 * 配置位于后台「系统设置 → 审核设置」（site_settings 分组 audit）：
 *   precheck_enabled    预审总开关
 *   precheck_model      图像理解模型（默认 doubao-seed-2-0-lite-260428）
 *   precheck_prompt     审核提示词（业务规则，后台可改）
 *   precheck_fail_open  审核服务异常时是否放行（true=放行不堵业务，false=拒绝）
 *
 * API Key 复用「API 配置」里的 ark_api_key（Ark 同一密钥可调用视觉模型）。
 *
 * 回复格式：提示词强制模型只输出 JSON：{"pass":true|false,"reason":"..."}
 * 代码侧做防御性提取（允许模型夹带 markdown/多余文字）。
 */
final class ImagePrecheckService
{
    /** 业务异常码：图片未通过预审 */
    public const CODE_REJECTED = 4006;

    /** 默认审核提示词（后台未配置时使用） */
    public const DEFAULT_PROMPT = '你是图片合规审核员。用户上传这张图片是用来生成个人头像的（图生图）。请判断该图片是否同时满足以下条件：'
        . '1. 内容合规：不含色情、裸露、暴力、血腥、恐怖、政治敏感、违法违规、广告引流、未成年人不当内容；'
        . '2. 适合作为头像：应为真人自拍或人物照片（人脸/人物半身/人像写真均可），'
        . '风景、动植物、物品、游戏截图、软件界面、纯文字图、二维码、表情包、非人物插画等均不符合。';

    /** 强制模型输出固定 JSON 格式的后缀（不允许后台修改，保证可解析） */
    private const FORMAT_SUFFIX = "\n\n请严格只输出以下 JSON，不要输出 JSON 以外的任何内容，不要使用 markdown 代码块：\n"
        . '{"pass":true 或 false,"reason":"不超过30字的中文原因，说明通过或拒绝的理由"}';

    /**
     * 预审是否开启（开关开 + API Key 已配置才算真正可用）
     */
    public static function enabled(): bool
    {
        return (bool)Config::get('audit', 'precheck_enabled', false)
            && self::apiKey() !== '';
    }

    /**
     * 审核一张图片
     *
     * @param string $imageUrl 公网 URL 或站内 /uploads/... 相对路径
     * @return array{ok:bool,pass:bool,reason:string}
     *               ok   = 审核服务调用并解析成功（网络/模型/解析失败时为 false）
     *               pass = ok 为 true 时，图片是否通过审核
     */
    public static function review(string $imageUrl): array
    {
        $apiKey = self::apiKey();
        if ($apiKey === '') {
            return ['ok' => false, 'pass' => false, 'reason' => '未配置豆包 API Key'];
        }

        // 站内相对路径补全为公网绝对地址（豆包服务器需能拉取到图片）
        if (!preg_match('#^https?://#i', $imageUrl)) {
            $imageUrl = SeoService::absoluteUrl($imageUrl);
        }

        $baseUrl = rtrim((string)Config::get('api', 'ark_base_url', 'https://ark.cn-beijing.volces.com/api/v3'), '/');
        // 模型名：后台留空时回退默认（DB 中存空字符串时 Config 默认值不生效，需在此兜底）
        $model = trim((string)Config::get('audit', 'precheck_model', ''));
        if ($model === '') {
            $model = 'doubao-seed-2-0-lite-260428';
        }
        $prompt  = self::buildPrompt();

        $body = [
            'model' => $model,
            'input' => [[
                'role'    => 'user',
                'content' => [
                    ['type' => 'input_image', 'image_url' => $imageUrl],
                    ['type' => 'input_text', 'text' => $prompt],
                ],
            ]],
        ];
        $bodyJson = json_encode($body, JSON_UNESCAPED_UNICODE);
        if ($bodyJson === false) {
            Log::error('precheck json encode failed', ['err' => json_last_error_msg()]);
            return ['ok' => false, 'pass' => false, 'reason' => '审核请求构造失败（提示词含非法字符）'];
        }

        $ch = curl_init($baseUrl . '/responses');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS     => $bodyJson,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        $resp     = (string)curl_exec($ch);
        $errno    = curl_errno($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            Log::error('precheck curl error', ['errno' => $errno, 'msg' => curl_strerror($errno)]);
            return ['ok' => false, 'pass' => false, 'reason' => '审核服务连接失败'];
        }

        $decoded = json_decode($resp, true);
        if (!is_array($decoded)) {
            Log::error('precheck invalid json', ['http' => $httpCode, 'body' => mb_substr($resp, 0, 500)]);
            return ['ok' => false, 'pass' => false, 'reason' => '审核服务响应异常'];
        }

        // Ark 错误体：{"error":{"code":"...","message":"..."}}
        if ($httpCode >= 400 || isset($decoded['error'])) {
            $msg = $decoded['error']['message'] ?? ('HTTP ' . $httpCode);
            Log::error('precheck api error', ['http' => $httpCode, 'error' => $msg]);
            return ['ok' => false, 'pass' => false, 'reason' => '审核服务错误：' . $msg];
        }

        // /responses 结果在 output[].content[].text（类型 output_text），做防御性遍历
        $text = self::extractText($decoded);
        if ($text === '') {
            Log::error('precheck empty text', ['resp' => mb_substr($resp, 0, 800)]);
            return ['ok' => false, 'pass' => false, 'reason' => '审核结果为空'];
        }

        $parsed = self::parseVerdict($text);
        if ($parsed === null) {
            Log::error('precheck parse failed', ['text' => mb_substr($text, 0, 500)]);
            return ['ok' => false, 'pass' => false, 'reason' => '审核结果无法识别'];
        }

        return [
            'ok'     => true,
            'pass'   => $parsed['pass'],
            'reason' => $parsed['reason'],
        ];
    }

    /**
     * 失败放行策略：true=审核服务异常时放行（不堵业务），false=异常时拒绝
     */
    public static function failOpen(): bool
    {
        return (bool)Config::get('audit', 'precheck_fail_open', true);
    }

    private static function apiKey(): string
    {
        return trim((string)Config::get('api', 'ark_api_key', ''));
    }

    private static function buildPrompt(): string
    {
        $custom = trim((string)Config::get('audit', 'precheck_prompt', ''));
        return ($custom !== '' ? $custom : self::DEFAULT_PROMPT) . self::FORMAT_SUFFIX;
    }

    /**
     * 从 /responses 响应中提取模型文本
     * 结构：output: [ {type:"message", content:[{type:"output_text",text:"..."}]},
     *                {type:"output_text", text:"..."} ]
     */
    private static function extractText(array $decoded): string
    {
        $parts = [];
        foreach (($decoded['output'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            // 形态一：output 项本身就是 output_text
            if (($item['type'] ?? '') === 'output_text' && !empty($item['text'])) {
                $parts[] = (string)$item['text'];
            }
            // 形态二：message → content[]
            foreach (($item['content'] ?? []) as $c) {
                if (is_array($c) && in_array($c['type'] ?? '', ['output_text', 'text'], true) && !empty($c['text'])) {
                    $parts[] = (string)$c['text'];
                }
            }
        }
        return trim(implode("\n", $parts));
    }

    /**
     * 从模型回复中提取 {"pass":...,"reason":...}
     * 允许模型夹带 ```json 代码块或前后缀文字
     *
     * @return array{pass:bool,reason:string}|null
     */
    private static function parseVerdict(string $text): ?array
    {
        if (!preg_match('/\{[\s\S]*\}/', $text, $m)) {
            return null;
        }
        $j = json_decode($m[0], true);
        if (!is_array($j) || !array_key_exists('pass', $j)) {
            return null;
        }
        $pass = filter_var($j['pass'], FILTER_VALIDATE_BOOLEAN);
        $reason = trim((string)($j['reason'] ?? ''));
        if ($reason === '') {
            $reason = $pass ? '图片符合头像要求' : '图片不符合头像要求';
        }
        return ['pass' => $pass, 'reason' => mb_substr($reason, 0, 100)];
    }
}
