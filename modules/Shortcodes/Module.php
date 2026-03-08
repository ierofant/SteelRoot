<?php
namespace Modules\Shortcodes;

use Core\Container;
use Core\Renderer;
use Core\Router;
use Modules\Shortcodes\Handlers\AgeVerification;
use Modules\Shortcodes\Handlers\Gallery;

/**
 * Shortcodes module.
 *
 * Boots during Kernel module registration phase.
 * Wires ShortcodeRegistry into Renderer::addContentFilter() so that
 * shortcodes are processed on every rendered content template — with
 * zero coupling to any specific module (Articles, Pages, etc.).
 *
 * CSS assets are injected via Slot 'head_end'.
 * JS  assets are injected via Slot 'body_end'.
 */
class Module
{
    public function __construct(private string $path) {}

    public function register(Container $container, Router $router): void
    {
        // Register built-in handlers with their associated assets
        ShortcodeRegistry::register(
            'age_verification',
            [AgeVerification::class, 'render'],
            ['/assets/css/age-verification.css'],
            ['/assets/js/age-verification.js']
        );

        ShortcodeRegistry::register(
            'nanogallery',
            [Gallery::class, 'render'],
            ['/assets/css/gallery.css'],
            ['/assets/js/gallery.js']
        );

        // Hook into Renderer content pipeline (generic, not Articles-specific)
        Renderer::addContentFilter(
            fn(string $html, array $data): string => ShortcodeRegistry::process($html)
        );
    }
}
