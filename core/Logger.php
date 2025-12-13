<?php
namespace Core;

class Logger
{
    public static function log(string $message): void
    {
        $file = APP_ROOT . '/storage/logs/app.log';
        if (!is_dir(dirname($file))) {
            @mkdir(dirname($file), 0775, true);
        }
        $ts = date('Y-m-d H:i:s');
        @file_put_contents($file, "[{$ts}] {$message}\n", FILE_APPEND);
    }
}
