<?php
declare(strict_types=1);

namespace App\Core;

/**
 * 简单文件日志
 * 日志根目录：ROOT_PATH/../storage/logs
 *
 * 用法：
 *   Log::info('user login', ['user_id' => 1]);
 *   Log::error('ark api failed', ['body' => $resp]);
 */
final class Log
{
    private static function write(string $level, string $message, array $context = []): void
    {
        $dir = ROOT_PATH . '/../storage/logs';
        if (!is_dir($dir) && !@mkdir($dir, 0777, true) && !is_dir($dir)) {
            return;
        }
        $file = $dir . '/' . date('Y-m-d') . '.log';
        $time = date('Y-m-d H:i:s');
        $ctx  = empty($context) ? '' : ' ' . json_encode($context, JSON_UNESCAPED_UNICODE);
        $line = "[{$time}] [{$level}] {$message}{$ctx}\n";
        @file_put_contents($file, $line, FILE_APPEND);
    }

    public static function debug(string $message, array $context = []): void
    {
        self::write('DEBUG', $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::write('INFO', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::write('WARNING', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::write('ERROR', $message, $context);
    }
}
