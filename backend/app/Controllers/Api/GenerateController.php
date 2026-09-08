<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Models\GenerationRecord;
use App\Services\AvatarGenerateService;
use App\Services\ObjectStorageService;

/**
 * 头像生成 API
 */
final class GenerateController
{
    /**
     * POST /api/v1/avatar/generate
     * 同步入口：扣积分 + 投递队列
     */
    public function generate(Request $req, Response $res, array $params): void
    {
        $image   = (string)$req->input('image', '');
        $payload = $req->only('style_id', 'color_id', 'shape_id', 'prompt_id', 'prompt_override', 'is_public', 'sub_user_id');
        $subUserIdentifier = $req->input('sub_user_id');
        if (!$subUserIdentifier) {
            $subUserIdentifier = null;
        }

        if ($image === '') {
            $res->error(4001, 'image is required');
            return;
        }
        if (!preg_match('#^https?://#i', $image) && !str_starts_with($image, '/')) {
            $res->error(4001, 'image must be a public URL or an uploaded path (/uploads/...)');
            return;
        }

        /** @var array{key:\App\Models\UserKey,user:\App\Models\User} $auth */
        $auth = $req->load('key');
        if (!$auth) {
            $res->error(4002, 'Unauthorized', 401);
            return;
        }
        // 强制手机验证：开发者账号未验证手机时拒绝生成
        if (\App\Services\SmsService::forceVerify() && (int)($auth['user']->phone_verified ?? 0) !== 1) {
            $res->error(4003, '账号未完成手机验证，请先在控制台「个人资料」绑定手机号', 403);
            return;
        }

        try {
            $result = AvatarGenerateService::createTask(
                (int)$auth['user']->id,
                (int)$auth['key']->id,
                $subUserIdentifier,
                $image,
                $payload
            );
            // 计入日限额 + used_today
            \App\Services\RateLimitService::consume($auth['key']);
        } catch (\RuntimeException $e) {
            $code = (int)$e->getCode();
            $http = $code === 4003 ? 403 : ($code === 4002 ? 401 : ($code === 4006 ? 422 : 500));
            $res->error($code ?: 5000, $e->getMessage(), $http);
            return;
        } catch (\Throwable $e) {
            $res->error(5000, 'Server error: ' . $e->getMessage(), 500);
            return;
        }

        $res->json([
            'record_id'        => $result['record_id'],
            'status'           => 'processing',
            'cost_points'      => $result['cost_points'],
            'remaining_points' => $result['remaining_points'],
        ]);
    }

    /**
     * POST /api/v1/avatar/generate-sync  (multipart/form-data)
     * 上传即生成：上传本地图片文件，服务端转存后在当前请求内同步完成生成，直接返回头像地址
     * 表单字段：
     *   file        必填，图片文件 jpg/png/webp ≤10MB
     *   style_id    选填，风格 ID
     *   color_id    选填，颜色 ID
     *   shape_id    选填，形状 ID
     *   prompt_id   选填，提示词模板 ID
     *   is_public   选填，1/0，是否入公共池
     *   sub_user_id 选填，子用户标识
     * 注意：生成约需数秒至数十秒，客户端超时请设 ≥120s
     */
    public function generateSync(Request $req, Response $res, array $params): void
    {
        /** @var array{key:\App\Models\UserKey,user:\App\Models\User} $auth */
        $auth = $req->load('key');
        if (!$auth) {
            $res->error(4002, 'Unauthorized', 401);
            return;
        }
        // 强制手机验证：开发者账号未验证手机时拒绝生成
        if (\App\Services\SmsService::forceVerify() && (int)($auth['user']->phone_verified ?? 0) !== 1) {
            $res->error(4003, '账号未完成手机验证，请先在控制台「个人资料」绑定手机号', 403);
            return;
        }

        // 1) 校验上传文件
        $file = $req->file('file');
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $res->error(4001, 'No file uploaded (multipart field: file)');
            return;
        }
        $maxBytes  = 10 * 1024 * 1024;
        $allowExt  = ['jpg', 'jpeg', 'png', 'webp'];
        $allowMime = ['image/jpeg', 'image/png', 'image/webp'];
        if ($file['size'] > $maxBytes) {
            $res->error(4001, 'File too large (max 10MB)');
            return;
        }
        $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowExt, true)) {
            $res->error(4001, 'Unsupported file type (jpg/png/webp only)');
            return;
        }
        $mime = function_exists('mime_content_type') ? mime_content_type($file['tmp_name']) : ($file['type'] ?? '');
        if ($mime && !in_array($mime, $allowMime, true)) {
            $res->error(4001, 'MIME not allowed: ' . $mime);
            return;
        }

        // 2) 原图转存到自有存储
        $originPath = 'uploads/' . date('Ym') . '/' . uniqid('src_', true) . '.' . $ext;
        try {
            $originUrl = ObjectStorageService::upload($file['tmp_name'], $originPath);
        } catch (\Throwable $e) {
            $res->error(5000, 'Upload failed: ' . $e->getMessage(), 500);
            return;
        }

        // 3) 同步生成（豆包生成通常耗时数秒~数十秒，放宽 PHP 执行时间）
        @set_time_limit(120);
        $payload = $req->only('style_id', 'color_id', 'shape_id', 'prompt_id', 'prompt_override', 'is_public');
        $subUserIdentifier = $req->input('sub_user_id');

        try {
            $result = AvatarGenerateService::generateSync(
                (int)$auth['user']->id,
                (int)$auth['key']->id,
                $subUserIdentifier ? (string)$subUserIdentifier : null,
                $originUrl,
                $payload
            );
            // 计入日限额 + used_today
            \App\Services\RateLimitService::consume($auth['key']);
        } catch (\RuntimeException $e) {
            $code = (int)$e->getCode();
            $http = $code === 4003 ? 403 : ($code === 5002 ? 502 : ($code === 4006 ? 422 : 500));
            $res->error($code ?: 5000, $e->getMessage(), $http);
            return;
        } catch (\Throwable $e) {
            $res->error(5000, 'Server error: ' . $e->getMessage(), 500);
            return;
        }

        $res->json($result);
    }

    /**
     * GET /api/v1/avatar/generate/{record_id}
     * 查询生成结果
     */
    public function query(Request $req, Response $res, array $params): void
    {
        $recordId = (int)($params['record_id'] ?? 0);
        $record   = GenerationRecord::find($recordId);
        if (!$record) {
            $res->error(4004, 'Record not found', 404);
            return;
        }

        /** @var array{key:\App\Models\UserKey,user:\App\Models\User} $auth */
        $auth = $req->load('key');
        if (!$auth || (int)$record->user_id !== (int)$auth['user']->id) {
            $res->error(4003, 'Forbidden', 403);
            return;
        }

        $avatarId = $record->avatar_id ? (int)$record->avatar_id : null;
        $url      = '';
        $thumbUrl = '';
        if ($avatarId && ($avatar = \App\Models\Avatar::find($avatarId))) {
            $url      = (string)$avatar->result_url;
            $thumbUrl = (string)$avatar->result_thumb_url;
        }

        $res->json([
            'record_id'   => $recordId,
            'status'       => (string)$record->status,
            'avatar_id'    => $avatarId,
            'url'          => $url,
            'thumb_url'    => $thumbUrl,
            'cost_points'  => (int)$record->cost_points,
        ]);
    }
}
