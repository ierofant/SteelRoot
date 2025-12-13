<?php
namespace Core;

class Slot
{
    protected static array $slots = [];

    public static function register(string $name, callable $callback): void
    {
        self::$slots[$name][] = $callback;
    }

    public static function render(string $name): void
    {
        if (empty(self::$slots[$name])) {
            return;
        }
        foreach (self::$slots[$name] as $callback) {
            try {
                $html = $callback();
                if (is_string($html)) {
                    echo $html;
                }
            } catch (\Throwable $e) {
                // swallow slot errors to avoid breaking layout
                continue;
            }
        }
    }
}
