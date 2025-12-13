<?php
namespace App\Services;

class SecurityLog
{
    private static string $file = APP_ROOT . '/storage/logs/security.log';

    public static function filePath(): string
    {
        return self::$file;
    }

    public static function log(string $type, array $payload = []): void
    {
        $dir = dirname(self::$file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $record = [
            'ts' => date('Y-m-d H:i:s'),
            'type' => $type,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            'ua' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'path' => $_SERVER['REQUEST_URI'] ?? '',
            'data' => $payload,
        ];
        @file_put_contents(self::$file, json_encode($record, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
    }

    public static function tail(int $lines = 200): array
    {
        $file = self::$file;
        if (!file_exists($file)) {
            return [];
        }
        $fh = @fopen($file, 'r');
        if (!$fh) {
            return [];
        }
        $buffer = '';
        $pos = -1;
        $lineCount = 0;
        $result = [];
        while ($lineCount < $lines && fseek($fh, $pos, SEEK_END) === 0) {
            $char = fgetc($fh);
            if ($char === "\n") {
                $lineCount++;
                if ($buffer !== '') {
                    $decoded = json_decode(strrev($buffer), true);
                    if ($decoded) {
                        $result[] = $decoded;
                    }
                    $buffer = '';
                }
            } else {
                $buffer .= $char;
            }
            $pos--;
        }
        if ($buffer !== '') {
            $decoded = json_decode(strrev($buffer), true);
            if ($decoded) {
                $result[] = $decoded;
            }
        }
        fclose($fh);
        return array_reverse($result);
    }
}
