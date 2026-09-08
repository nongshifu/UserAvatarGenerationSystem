<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Services\ObjectStorageService;

final class UploadController
{
    /**
     * POST /api/v1/upload  (multipart/form-data)
     * 表单字段 file，支持 jpg/png/webp，≤10MB
     */
    public function upload(Request $req, Response $res, array $params): void
    {
        $file = $req->file('file');
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $res->error(4001, 'No file uploaded');
            return;
        }
        $maxBytes  = 10 * 1024 * 1024;
        $allowExt  = ['jpg', 'jpeg', 'png', 'webp'];
        $allowMime = ['image/jpeg', 'image/png', 'image/webp'];

        if ($file['size'] > $maxBytes) {
            $res->error(4001, 'File too large (max 10MB)');
            return;
        }
        $ext  = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowExt, true)) {
            $res->error(4001, 'Unsupported file type');
            return;
        }
        // 检测真实 MIME（不依赖 finfo 时回退到声明值）
        $mime = function_exists('mime_content_type') ? mime_content_type($file['tmp_name']) : ($file['type'] ?? '');
        if ($mime && !in_array($mime, $allowMime, true)) {
            $res->error(4001, 'MIME not allowed: ' . $mime);
            return;
        }

        // public_base 已是 /uploads，targetPath 不要再带 uploads 段
        $targetPath = 'sources/' . date('Ym') . '/' . uniqid('up_', true) . '.' . $ext;
        try {
            $url = ObjectStorageService::upload($file['tmp_name'], $targetPath);
        } catch (\Throwable $e) {
            $res->error(5000, 'Upload failed: ' . $e->getMessage(), 500);
            return;
        }
        $res->json(['url' => $url]);
    }
}
