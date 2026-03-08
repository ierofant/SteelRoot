<?php
namespace Modules\Shortcodes;

/**
 * Lightweight shortcode parser.
 * Supports single tags: {tag}
 * And paired tags:      {tag}content{/tag}
 */
class ShortcodeParser
{
    private array $handlers = [];

    public function register(string $tag, callable $handler): void
    {
        $this->handlers[$tag] = $handler;
    }

    public function parse(string $content): string
    {
        // Paired first: {tag}...{/tag}
        $content = preg_replace_callback(
            '/\{([a-z_]+)\}(.*?)\{\/\1\}/is',
            function (array $m): string {
                $tag = $m[1];
                return isset($this->handlers[$tag])
                    ? call_user_func($this->handlers[$tag], $m[2])
                    : $m[0];
            },
            $content
        );

        // Then single: {tag}
        $content = preg_replace_callback(
            '/\{([a-z_]+)\}/',
            function (array $m): string {
                $tag = $m[1];
                return isset($this->handlers[$tag])
                    ? call_user_func($this->handlers[$tag], '')
                    : $m[0];
            },
            $content
        );

        return $content;
    }
}
