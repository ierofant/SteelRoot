<?php
namespace Core\Shortcode;

/**
 * Lightweight shortcode parser.
 *
 * Supports:
 *   {tag}            — single shortcode, handler called with ''
 *   {tag}inner{/tag} — paired shortcode, handler called with inner content
 *
 * Unknown tags are left untouched.
 */
class ShortcodeParser
{
    /** @var callable[] tag => callable(string $inner): string */
    private array $handlers = [];

    public function register(string $tag, callable $handler): void
    {
        $this->handlers[$tag] = $handler;
    }

    public function parse(string $content): string
    {
        // Paired first so single pattern doesn't eat opening tags
        $content = preg_replace_callback(
            '/\{([a-z_][a-z0-9_]*)\}(.*?)\{\/\1\}/is',
            function (array $m): string {
                return isset($this->handlers[$m[1]])
                    ? ($this->handlers[$m[1]])($m[2])
                    : $m[0];
            },
            $content
        );

        $content = preg_replace_callback(
            '/\{([a-z_][a-z0-9_]*)\}/',
            function (array $m): string {
                return isset($this->handlers[$m[1]])
                    ? ($this->handlers[$m[1]])('')
                    : $m[0];
            },
            $content
        );

        return $content;
    }
}
