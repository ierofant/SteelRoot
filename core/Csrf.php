<?php
namespace Core;

class Csrf
{
    private const KEY = 'csrf_tokens';

    public static function token(string $context): string
    {
        if (!isset($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = [];
        }
        if (empty($_SESSION[self::KEY][$context])) {
            $_SESSION[self::KEY][$context] = bin2hex(random_bytes(16));
        }
        return $_SESSION[self::KEY][$context];
    }

    public static function check(string $context, ?string $token): bool
    {
        return isset($_SESSION[self::KEY][$context]) && hash_equals($_SESSION[self::KEY][$context], (string)$token);
    }
}
