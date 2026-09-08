<?php
declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Models\GenerationRecord;
use App\Services\ObjectStorageService;
use App\Services\SiteSettingService;
use App\Services\UserAuthService;
use App\Services\UserService;
use App\Services\WebGenerateService;

/**
 * 官网生成头像
 * - GET  /console/generate   表单
 * - POST /console/generate   提交（上传URL + 风格/颜色/形状 + is_public）
 * - GET  /console/generate/{id}  查询生成状态
 */
final class GenerateController
{
    private function userOrFail(Response $res): ?\App\Models\User
    {
        $user = UserAuthService::currentUser();
        if (!$user) {
            $res->redirect('/login');
            return null;
        }
        return $user;
    }

    /** GET /console/generate */
    public function form(Request $req, Response $res, array $params): void
    {
        $user = $this->userOrFail($res);
        if (!$user) {
            return;
        }
        $dims = UserService::dimensionOptions();
        View::display($res, 'console/generate', [
            'title'       => '生成头像 - 头像引擎',
            'siteName'    => SiteSettingService::get('basic', 'site_name', '头像引擎'),
            'currentUser' => $user,
            'balance'     => UserService::balance((int)$user->id),
            'styles'      => $dims['styles'],
            'colors'      => $dims['colors'],
            'shapes'      => $dims['shapes'],
            'needPhoneVerify' => \App\Services\SmsService::forceVerify() && (int)($user->phone_verified ?? 0) !== 1,
            'error'       => '',
        ]);
    }

    /** POST /console/generate */
    public function create(Request $req, Response $res, array $params): void
    {
        $user = $this->userOrFail($res);
        if (!$user) {
            return;
        }

        // 强制手机验证：未验证手机禁止生成
        if (\App\Services\SmsService::forceVerify() && (int)($user->phone_verified ?? 0) !== 1) {
            $this->renderError($res, $user, '请先在「个人资料」中绑定并验证手机号后再生成头像');
            return;
        }

        // 优先处理本地上传文件；也兼容直接传 image URL
        $image = '';
        $file = $req->file('file');
        if ($file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            try {
                $image = $this->storeUpload($file);
            } catch (\RuntimeException $e) {
                $this->renderError($res, $user, $e->getMessage());
                return;
            }
        } else {
            $image = trim((string)$req->input('image', ''));
        }

        if ($image === '' || (!preg_match('#^https?://#i', $image) && !str_starts_with($image, '/'))) {
            $this->renderError($res, $user, '请先上传一张照片');
            return;
        }
        $payload = [
            'style_id' => (int)$req->input('style_id', 0),
            'color_id' => (int)$req->input('color_id', 0),
            'shape_id' => (int)$req->input('shape_id', 0),
            'is_public' => $req->input('is_public') !== null,
        ];
        try {
            $result = WebGenerateService::create((int)$user->id, $image, $payload);
            $res->redirect('/console/generate/' . $result['record_id']);
        } catch (\RuntimeException $e) {
            $this->renderError($res, $user, $e->getMessage());
        } catch (\Throwable $e) {
            $this->renderError($res, $user, '生成失败：' . $e->getMessage());
        }
    }

    /**
     * 校验并转存上传的原图，返回可访问 URL
     */
    private function storeUpload(array $file): string
    {
        $maxBytes  = 10 * 1024 * 1024;
        $allowExt  = ['jpg', 'jpeg', 'png', 'webp'];
        $allowMime = ['image/jpeg', 'image/png', 'image/webp'];

        if (($file['size'] ?? 0) > $maxBytes) {
            throw new \RuntimeException('图片不能超过 10MB');
        }
        $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($ext, $allowExt, true)) {
            throw new \RuntimeException('仅支持 jpg / png / webp 格式');
        }
        $mime = function_exists('mime_content_type') ? mime_content_type($file['tmp_name']) : ($file['type'] ?? '');
        if ($mime && !in_array($mime, $allowMime, true)) {
            throw new \RuntimeException('图片格式不正确：' . $mime);
        }
        // 注意：public_base 已是 /uploads，targetPath 不要再带 uploads 段，否则 URL 变成 /uploads/uploads/...
        $targetPath = 'sources/' . date('Ym') . '/' . uniqid('src_', true) . '.' . $ext;
        return ObjectStorageService::upload($file['tmp_name'], $targetPath);
    }

    /** GET /console/generate/{id} */
    public function status(Request $req, Response $res, array $params): void
    {
        $user = $this->userOrFail($res);
        if (!$user) {
            return;
        }
        $recordId = (int)($params['id'] ?? 0);
        $record = GenerationRecord::find($recordId);
        if (!$record || (int)$record->user_id !== (int)$user->id) {
            $res->notFound('记录不存在');
            return;
        }
        $arr = $record->toArray();
        $avatar = $arr['avatar_id'] ? \App\Models\Avatar::find((int)$arr['avatar_id']) : null;
        View::display($res, 'console/generate_status', [
            'title'       => '生成中 - 头像引擎',
            'siteName'    => SiteSettingService::get('basic', 'site_name', '头像引擎'),
            'currentUser' => $user,
            'record'      => $arr,
            'avatar'      => $avatar ? $avatar->toArray() : null,
        ]);
    }

    private function renderError(Response $res, \App\Models\User $user, string $error): void
    {
        $dims = UserService::dimensionOptions();
        View::display($res, 'console/generate', [
            'title'       => '生成头像 - 头像引擎',
            'siteName'    => SiteSettingService::get('basic', 'site_name', '头像引擎'),
            'currentUser' => $user,
            'balance'     => UserService::balance((int)$user->id),
            'styles'      => $dims['styles'],
            'colors'      => $dims['colors'],
            'shapes'      => $dims['shapes'],
            'error'       => $error,
        ]);
    }
}
