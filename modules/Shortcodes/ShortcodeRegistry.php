<?php
namespace Modules\Shortcodes;

use Core\Shortcode\ShortcodeParser;
use Core\Slot;

/**
 * Global registry for shortcode handlers.
 *
 * Modules call ShortcodeRegistry::register() in their boot phase.
 * The Shortcodes module wires this into Renderer::addContentFilter().
 *
 * Asset injection:
 *   CSS → queued, flushed to Slot 'head_end' after first process() call
 *   JS  → registered to Slot 'body_end' (deduplicated)
 */
class ShortcodeRegistry
{
    /** @var array<string, array{handler: callable, css: string[], js: string[]}> */
    private static array $handlers = [];

    private static array $cssQueue    = [];
    private static array $jsRegistered = [];
    private static bool  $headSlotSet  = false;

    // ── Registration ────────────────────────────────────────────────────────

    public static function register(
        string   $tag,
        callable $handler,
        array    $css = [],
        array    $js  = []
    ): void {
        self::$handlers[$tag] = compact('handler', 'css', 'js');
    }

    // ── Processing ──────────────────────────────────────────────────────────

    /**
     * Run all registered shortcodes against $content.
     * Queues CSS/JS for any shortcodes that were actually used.
     */
    public static function process(string $content): string
    {
        if (empty(self::$handlers)) {
            return $content;
        }

        $parser = new ShortcodeParser();

        foreach (self::$handlers as $tag => $def) {
            $parser->register($tag, function (string $inner) use ($def): string {
                self::queueAssets($def['css'], $def['js']);
                return ($def['handler'])($inner);
            });
        }

        $result = $parser->parse($content);

        // Register CSS into head_end slot once, after processing
        if (!self::$headSlotSet && !empty(self::$cssQueue)) {
            self::$headSlotSet = true;
            $links = self::buildLinkTags(self::$cssQueue);
            Slot::register('head_end', fn() => $links);
        }

        return $result;
    }

    // ── Internals ────────────────────────────────────────────────────────────

    private static function queueAssets(array $css, array $js): void
    {
        foreach ($css as $url) {
            self::$cssQueue[$url] = $url;
        }
        foreach ($js as $url) {
            if (!isset(self::$jsRegistered[$url])) {
                self::$jsRegistered[$url] = true;
                $src = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
                Slot::register('body_end', fn() => "<script src=\"{$src}\"></script>\n");
            }
        }
    }

    private static function buildLinkTags(array $urls): string
    {
        $out = '';
        foreach ($urls as $url) {
            $out .= '<link rel="stylesheet" href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">' . "\n";
        }
        return $out;
    }
}
