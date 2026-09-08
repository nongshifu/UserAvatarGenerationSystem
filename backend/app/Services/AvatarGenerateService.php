<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\DB;
use App\Core\Log;
use App\Core\RedisClient;
use App\Models\Avatar;
use App\Models\GenerationRecord;
use App\Models\PromptTemplate;
use App\Models\Style;
use App\Models\Color;
use App\Models\Shape;
use App\Models\SubUser;
use App\Services\PresetPromptService;

/**
 * 头像生成引擎
 *
 * P0 关键路径：
 *   createTask()    → 同步入口，扣积分 + 写 pending 记录 + 投递队列
 *   consume()       → 队列 Worker 调用，调豆包 + 转存 + 入三池
 *   handleFailure() → 失败退积分
 */
final class AvatarGenerateService
{
    public const QUEUE_NAME = 'avatar:generate';

    /**
     * 同步：创建生成任务
     * 调用方须已鉴权（Key + User），积分预扣，投递异步队列
     *
     * @return array{record_id:int,cost_points:int,remaining_points:int}
     */
    public static function createTask(
        int $userId,
        ?int $keyId,
        ?string $subUserIdentifier,
        string $originImageUrl,
        array $params, // {style_id,color_id,shape_id,prompt_id?,prompt_override?,is_public?}
        bool $enqueue = true
    ): array {
        // 上传图片 AI 预审：扣积分/入队前先调豆包图像理解判断图片是否合规、是否适合做头像
        // 未通过直接拒绝（不扣积分）；审核服务异常时按后台 fail_open 策略决定放行/拒绝
        if (ImagePrecheckService::enabled()) {
            $review = ImagePrecheckService::review($originImageUrl);
            if (!$review['ok']) {
                Log::warning('image precheck unavailable', ['user_id' => $userId, 'reason' => $review['reason']]);
                if (!ImagePrecheckService::failOpen()) {
                    throw new \RuntimeException('图片审核服务暂时不可用，请稍后再试', ImagePrecheckService::CODE_REJECTED);
                }
            } elseif (!$review['pass']) {
                Log::info('image precheck rejected', ['user_id' => $userId, 'reason' => $review['reason']]);
                throw new \RuntimeException('图片未通过审核：' . $review['reason'], ImagePrecheckService::CODE_REJECTED);
            }
        }

        // 解析参数 + 计算消耗
        $styleId  = (int)($params['style_id'] ?? 0);
        $colorId  = (int)($params['color_id'] ?? 0);
        $shapeId  = (int)($params['shape_id'] ?? 0);
        $promptId = (int)($params['prompt_id'] ?? 0);
        $override = (string)($params['prompt_override'] ?? '');
        $isPublic = (bool)($params['is_public'] ?? true);

        $cost = self::calcCost($styleId);

        // 创建 GenerationRecord（pending）
        $record = GenerationRecord::create([
            'user_id'      => $userId,
            'key_id'       => $keyId,
            'sub_user_id'  => null, // 稍后入 sub_users 后回填
            'avatar_id'    => null,
            'prompt_id'    => $promptId ?: null,
            'style_id'     => $styleId ?: null,
            'color_id'     => $colorId ?: null,
            'shape_id'     => $shapeId ?: null,
            'params'       => json_encode($params, JSON_UNESCAPED_UNICODE),
            'origin_image_url' => $originImageUrl,
            'cost_points'  => $cost,
            'api_model'    => (string)Config::get('api', 'ark_model', 'doubao-seedream-5-0-260128'),
            'api_request_id'=> '',
            'api_response' => null,
            'status'       => 'pending',
            'error_msg'    => null,
            'retry_count'  => 0,
        ]);

        // 预扣积分（失败抛 RuntimeException → 调用方返回 4003）
        $remaining = PointService::consume($userId, $cost, (int)$record->getKey(), 'avatar generate');

        // 投递队列（同步模式下跳过，由调用方立即执行 runGeneration）
        $payload = [
            'record_id'         => (int)$record->getKey(),
            'user_id'           => $userId,
            'key_id'            => $keyId,
            'sub_user_identifier' => $subUserIdentifier,
            'origin_image_url'  => $originImageUrl,
            'is_public'         => $isPublic,
        ];
        if ($enqueue) {
            try {
                $queueLen = RedisClient::push(self::QUEUE_NAME, $payload);
                Log::info('task enqueued', [
                    'record_id' => (int)$record->getKey(),
                    'user_id'   => $userId,
                    'queue'     => self::QUEUE_NAME,
                    'queue_len' => $queueLen, // 入队后队列长度（>0 说明已积压在 Redis）
                ]);
            } catch (\Throwable $e) {
                // 入队失败：任务没有丢失（DB 有记录 + 积分已扣），记录错误便于手动补偿
                Log::error('task enqueue FAILED', [
                    'record_id' => (int)$record->getKey(),
                    'queue'     => self::QUEUE_NAME,
                    'err'       => $e->getMessage(),
                ]);
                throw $e;
            }
        } else {
            Log::info('task created (sync mode, no enqueue)', [
                'record_id' => (int)$record->getKey(),
                'user_id'   => $userId,
            ]);
        }

        return [
            'record_id'        => (int)$record->getKey(),
            'cost_points'      => $cost,
            'remaining_points' => $remaining,
            'payload'          => $payload,
        ];
    }

    /**
     * 同步生成：创建任务（扣积分，不投队列）并在当前进程立即执行完整生成流程
     * 供「上传即生成」API 使用，客户端无需轮询
     *
     * @return array{record_id:int,status:string,avatar_id:?int,url:string,thumb_url:string,audit_status:string,cost_points:int,remaining_points:int}
     * @throws \RuntimeException 积分不足(4003) 或生成失败(5002)
     */
    public static function generateSync(
        int $userId,
        ?int $keyId,
        ?string $subUserIdentifier,
        string $originImageUrl,
        array $params
    ): array {
        $task = self::createTask($userId, $keyId, $subUserIdentifier, $originImageUrl, $params, false);
        $recordId = $task['record_id'];
        $payload  = $task['payload'];

        $record = GenerationRecord::find($recordId);
        if (!$record) {
            throw new \RuntimeException('生成记录创建失败', 5000);
        }
        $record->setAttribute('status', 'processing');
        $record->save();

        try {
            $info = self::runGeneration($record, $payload);
        } catch (\Throwable $e) {
            // 同步模式：不重试入队（客户端正在等待），标记失败并立即退还积分
            $record->setAttribute('status', 'failed');
            $record->setAttribute('error_msg', mb_substr($e->getMessage(), 0, 500));
            $record->setAttribute('retry_count', 3);
            $record->save();
            try {
                PointService::refund($userId, (int)$task['cost_points'], $recordId, 'generate_refund', '同步生成失败退还');
            } catch (\Throwable $ignore) {
                Log::error('sync generate refund failed', ['record_id' => $recordId, 'err' => $ignore->getMessage()]);
            }
            Log::error('sync generate failed', ['record_id' => $recordId, 'err' => $e->getMessage()]);
            throw new \RuntimeException('生成失败：' . $e->getMessage(), 5002);
        }

        return [
            'record_id'        => $recordId,
            'status'           => 'success',
            'avatar_id'        => $info['avatar_id'],
            'url'              => $info['url'],
            'thumb_url'        => $info['thumb_url'],
            'audit_status'     => $info['audit_status'],
            'cost_points'      => $task['cost_points'],
            'remaining_points' => $task['remaining_points'],
        ];
    }

    /**
     * 异步：消费队列任务（Worker 调用）
     */
    public static function consume(array $payload): void
    {
        $recordId = (int)$payload['record_id'];
        $record   = GenerationRecord::find($recordId);
        if (!$record) {
            Log::error('queue: record not found', ['record_id' => $recordId]);
            return;
        }

        // 原子认领：仅当记录仍为 pending 时才能置为 processing。
        // 防止重复消息（重试/修复脚本重投）被并发处理两次；
        // 卡在 processing 的记录（Worker 崩溃）只能由修复命令先重置为 pending。
        $claimed = DB::raw(
            'UPDATE generation_records SET status = "processing", updated_at = NOW() WHERE id = :id AND status = "pending"',
            [':id' => $recordId]
        )->rowCount();
        if ($claimed < 1) {
            Log::info('consume skip (already claimed/done)', ['record_id' => $recordId, 'status' => $record->status]);
            return;
        }
        // 刷新内存模型状态
        $record = GenerationRecord::find($recordId);

        // 从 DB 重建 payload：重试/消息丢失恢复时队列里可能只有 record_id
        $params = [];
        if (!empty($record->params)) {
            $decoded = json_decode((string)$record->params, true);
            if (is_array($decoded)) {
                $params = $decoded;
            }
        }
        $fullPayload = [
            'record_id'           => $recordId,
            'user_id'             => (int)$record->user_id,
            'key_id'              => $record->key_id ? (int)$record->key_id : null,
            'sub_user_identifier' => null, // sub_user_id 已回填时无需重建；首次任务由原始 payload 携带
            'origin_image_url'    => (string)($record->origin_image_url ?: $payload['origin_image_url'] ?? ''),
            'is_public'           => array_key_exists('is_public', $payload)
                                    ? (bool)$payload['is_public']
                                    : (bool)($params['is_public'] ?? false),
        ];

        Log::info('consume start', [
            'record_id' => $recordId,
            'retry'     => (int)$record->retry_count,
            'origin'    => $fullPayload['origin_image_url'] !== '' ? 'db/payload' : 'MISSING!',
        ]);

        try {
            self::runGeneration($record, $fullPayload);
            Log::info('consume success', ['record_id' => $recordId]);
        } catch (\Throwable $e) {
            Log::error('consume failed -> handleFailure', ['record_id' => $recordId, 'err' => $e->getMessage()]);
            self::handleFailure($record, $e);
        }
    }

    /**
     * 执行完整生成流程：调豆包 → 转存 → 缩略图 → 审核 → 入三池 → 回填记录
     * 成功返回 avatar 信息；失败抛异常（由调用方决定重试/退款策略）
     *
     * @return array{avatar_id:int,url:string,thumb_url:string,audit_status:string}
     */
    private static function runGeneration(GenerationRecord $record, array $payload): array
    {
        $recordId = (int)$record->getKey();
        $prompt = self::buildPrompt($record);
        $originUrl = (string)$payload['origin_image_url'];
        if ($originUrl === '') {
            // 老数据（迁移前创建）记录里没有原图地址，队列消息也已丢失，无法恢复
            throw new \RuntimeException('任务缺少原图地址（origin_image_url 为空），无法生成，请重新提交', 5001);
        }

        // 调豆包
        Log::info('ark generateImage start', ['record_id' => $recordId, 'origin' => $originUrl]);
        $t0 = microtime(true);
        $result = ArkApiService::generateImage($originUrl, $prompt);
        Log::info('ark generateImage ok', [
            'record_id'  => $recordId,
            'request_id' => $result['request_id'] ?? '',
            'cost_ms'    => (int)((microtime(true) - $t0) * 1000),
        ]);
        $record->setAttribute('api_request_id', $result['request_id']);
        $record->setAttribute('api_response', json_encode($result['raw'], JSON_UNESCAPED_UNICODE));

        // 转存到自有存储
        $targetPath = sprintf('avatars/%s/%s.png', date('Ym'), $recordId);
        $resultUrl  = ObjectStorageService::transfer($result['result_url'], $targetPath);
        $thumbUrl   = ThumbnailService::make($resultUrl, $targetPath);
        Log::info('result transferred', ['record_id' => $recordId, 'url' => $resultUrl]);

        // 解析 sub_user（重试时 record 已回填 sub_user_id，直接复用）
        $subUserId = $record->sub_user_id ? (int)$record->sub_user_id : null;
        $keyId = $payload['key_id'] ? (int)$payload['key_id'] : null;
        $subIdentifier = $payload['sub_user_identifier'] ?? null;
        if ($subUserId === null && $keyId && $subIdentifier) {
            $subUser = SubUser::firstOrCreate($keyId, (string)$subIdentifier);
            $subUserId = (int)$subUser->getKey();
        }

        // 审核开关：若开启则调用内容安全服务，否则默认通过
        $auditEnabled = (bool)Config::get('audit', 'enabled', false);
        $auditStatus = 'approved';
        if ($auditEnabled) {
            $check = ContentSecurityService::checkImage($resultUrl);
            $auditStatus = $check['status'] === 'approved' ? 'approved' : 'rejected';
            if ($auditStatus === 'rejected') {
                Log::info('avatar audit rejected', ['record_id' => $recordId, 'reason' => $check['reason']]);
            }
        }

        // 创建 Avatar 主记录（含三池字段）
        $avatar = Avatar::create([
            'user_id'           => (int)$payload['user_id'],
            'key_id'            => $keyId,
            'sub_user_id'       => $subUserId,
            'origin_url'        => $originUrl,
            'result_url'        => $resultUrl,
            'result_thumb_url'  => $thumbUrl,
            'prompt_id'         => $record->prompt_id,
            'style_id'          => $record->style_id,
            'color_id'          => $record->color_id,
            'shape_id'          => $record->shape_id,
            'prompt_text'       => $prompt,
            'is_public'         => $auditStatus === 'approved' ? (int)($payload['is_public'] ?? false) : 0,
            'audit_status'      => $auditStatus,
            'status'            => 'success',
            'width'             => 0,
            'height'            => 0,
            'file_size'         => 0,
            'views'             => 0,
        ]);

        // 回填 record
        $record->setAttribute('avatar_id', (int)$avatar->getKey());
        $record->setAttribute('sub_user_id', $subUserId);
        $record->setAttribute('status', 'success');
        $record->save();

        // 审核驳回退积分（可配）
        if ($auditStatus === 'rejected' && (bool)Config::get('audit', 'refund_on_reject', true)) {
            try {
                PointService::refund((int)$record->user_id, (int)$record->cost_points, (int)$record->getKey(), 'audit_refund', '审核驳回退还');
            } catch (\Throwable $ignore) {
                Log::error('audit refund failed', ['record_id' => $recordId, 'err' => $ignore->getMessage()]);
            }
        }

        // 清随机头像缓存（按已用 filter 列表批量清理）
        self::clearRandomCaches();

        return [
            'avatar_id'    => (int)$avatar->getKey(),
            'url'          => $resultUrl,
            'thumb_url'    => $thumbUrl,
            'audit_status' => $auditStatus,
        ];
    }

    /**
     * 清理随机头像缓存：维护一个 filter 签名集合，逐个失效
     */
    private static function clearRandomCaches(): void
    {
        try {
            $sigs = RedisClient::sMembers('avatar:random:sigs');
            foreach ($sigs as $sig) {
                RedisClient::del('avatar:random:' . $sig);
            }
            RedisClient::del('avatar:random:sigs');
        } catch (\Throwable $ignore) {
        }
    }

    /**
     * 失败处理：重试 ≤3 次，仍失败退积分
     */
    private static function handleFailure(GenerationRecord $record, \Throwable $e): void
    {
        $retry = (int)$record->retry_count + 1;
        $record->setAttribute('retry_count', $retry);
        $record->setAttribute('error_msg', mb_substr($e->getMessage(), 0, 500));
        Log::error('avatar generate failed', ['record_id' => $record->getKey(), 'retry' => $retry, 'err' => $e->getMessage()]);

        if ($retry < 3) {
            // 重新入队（延迟简单实现：再次 push）
            $record->setAttribute('status', 'pending');
            $record->save();
            RedisClient::push(self::QUEUE_NAME, ['record_id' => (int)$record->getKey()]);
            Log::warning('task requeued for retry', ['record_id' => (int)$record->getKey(), 'retry' => $retry + 1]);
        } else {
            // 最终失败：退积分
            $record->setAttribute('status', 'failed');
            $record->save();
            Log::error('task final failed, refunding points', ['record_id' => (int)$record->getKey(), 'cost' => (int)$record->cost_points]);
            try {
                PointService::refund((int)$record->user_id, (int)$record->cost_points, (int)$record->getKey(), 'audit_refund', 'generate failed');
            } catch (\Throwable $ignore) {
                Log::error('refund failed', ['record_id' => $record->getKey(), 'err' => $ignore->getMessage()]);
            }
        }
    }

    /**
     * 拼接最终提示词，优先级：
     *   1. prompt_override（API 调用方自定义，最高优先级）
     *   2. 所选风格的完整预设提示词 styles.prompt（后台可编辑/重置）
     *   3. 基础模板 prompt_templates（未选风格时）+ 风格短后缀（老数据兼容）
     * 末尾统一追加颜色、形状后缀
     */
    private static function buildPrompt(GenerationRecord $record): string
    {
        // 1) 用户自定义提示词（存在 record.params JSON 中）
        $override = '';
        $paramsRaw = (string)($record->params ?? '');
        if ($paramsRaw !== '') {
            $decoded = json_decode($paramsRaw, true);
            if (is_array($decoded) && !empty($decoded['prompt_override'])) {
                $override = trim((string)$decoded['prompt_override']);
            }
        }

        // 2) 核心提示词
        $style = $record->style_id ? Style::find((int)$record->style_id) : null;
        if ($override !== '') {
            $core = $override;
        } elseif ($style && trim((string)($style->prompt ?? '')) !== '') {
            $core = trim((string)$style->prompt);
        } else {
            $template = $record->prompt_id ? PromptTemplate::find((int)$record->prompt_id) : PromptTemplate::getDefault();
            $core = ($template && trim((string)$template->prompt) !== '')
                ? trim((string)$template->prompt)
                : PresetPromptService::baseTemplateDefault();
            // 兼容老数据：风格无完整提示词、只有短后缀时追加
            if ($style && trim((string)($style->prompt_suffix ?? '')) !== '') {
                $core .= ', ' . trim((string)$style->prompt_suffix);
            }
        }

        // 3) 颜色 / 形状后缀
        $parts = [$core];
        if ($record->color_id && ($color = Color::find((int)$record->color_id)) && trim((string)$color->prompt_suffix) !== '') {
            $parts[] = trim((string)$color->prompt_suffix);
        }
        if ($record->shape_id && ($shape = Shape::find((int)$record->shape_id)) && trim((string)$shape->prompt_suffix) !== '') {
            $parts[] = trim((string)$shape->prompt_suffix);
        }
        return implode(', ', $parts);
    }

    /**
     * 消耗计算
     */
    private static function calcCost(int $styleId): int
    {
        if ($styleId && ($style = Style::find($styleId))) {
            $cost = (int)$style->cost_points;
            if ($cost > 0) {
                return $cost;
            }
        }
        return (int)Config::get('points', 'default_cost', 10);
    }
}
