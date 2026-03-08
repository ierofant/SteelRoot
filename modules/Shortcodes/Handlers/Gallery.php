<?php
namespace Modules\Shortcodes\Handlers;

/**
 * Handler for {nanogallery}...{/nanogallery} shortcode.
 *
 * Inner content: <a href="full"><img src="thumb"></a> elements,
 * optionally with data-ngdesc="caption" (Joomla nanogallery format).
 *
 * Also fixes migrated double-slash paths: //storage/ → /storage/
 */
class Gallery
{
    private static bool $lightboxRendered = false;

    public static function render(string $content): string
    {
        // Fix Joomla migration artefact
        $content = preg_replace('#(src|href)="//storage/#', '$1="/storage/', $content);
        $content = preg_replace("#(src|href)='//storage/#", "$1='/storage/", $content);
        $content = preg_replace('#data-ngthumb="//storage/#', 'data-ngthumb="/storage/', $content);
        $content = preg_replace("#data-ngthumb='//storage/#", "data-ngthumb='/storage/", $content);

        $items = self::parseItems($content);

        if (empty($items)) {
            return '<div class="gallery-empty">Галерея пуста</div>';
        }

        $html = self::buildGrid($items);
        if (!self::$lightboxRendered) {
            $html .= self::buildLightbox();
            self::$lightboxRendered = true;
        }

        return $html;
    }

    /** @return array<array{full:string, thumb:string, caption:string}> */
    private static function parseItems(string $html): array
    {
        $items = [];
        // Supports both formats:
        // 1) <a href="..." data-ngdesc="..."><img src="..."></a>
        // 2) <a href="..." data-ngthumb="..." data-ngdesc="..."></a> (Joomla nanogallery)
        preg_match_all(
            '#<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)</a>#is',
            $html,
            $matches,
            PREG_SET_ORDER
        );
        foreach ($matches as $m) {
            $anchor = (string)$m[0];
            $caption = '';
            if (preg_match('/data-ngdesc=["\']([^"\']*)["\']/i', $anchor, $dm)) {
                $caption = $dm[1];
            }

            $href = trim((string)$m[1]);
            $inner = (string)($m[2] ?? '');
            $thumb = '';

            if (preg_match('/data-ngthumb=["\']([^"\']+)["\']/i', $anchor, $tm)) {
                $thumb = trim((string)$tm[1]);
            } elseif (preg_match('#<img\s[^>]*src=["\']([^"\']+)["\'][^>]*>#is', $inner, $im)) {
                $thumb = trim((string)$im[1]);
            } else {
                $thumb = $href;
            }

            if ($href === '' && $thumb === '') {
                continue;
            }

            $full = self::resolveFullImagePath($href !== '' ? $href : $thumb, $thumb);
            $items[] = ['full' => $full, 'thumb' => $thumb, 'caption' => $caption];
        }
        return $items;
    }

    private static function resolveFullImagePath(string $full, string $thumb): string
    {
        $full = trim($full);
        $thumb = trim($thumb);

        if ($full !== '' && !self::looksLikeThumb($full) && self::fileExistsByPublicPath($full)) {
            return $full;
        }

        foreach ([$full, $thumb] as $source) {
            if ($source === '') {
                continue;
            }
            foreach (self::fullCandidates($source) as $candidate) {
                if (self::fileExistsByPublicPath($candidate)) {
                    return $candidate;
                }
            }
        }

        return $full !== '' ? $full : $thumb;
    }

    private static function looksLikeThumb(string $path): bool
    {
        return (bool)preg_match('/(?:[_-]thumb|[_-]thumbnail)(?=\.[a-z0-9]+(?:\?.*)?$)/i', $path);
    }

    /** @return string[] */
    private static function fullCandidates(string $path): array
    {
        $result = [];
        $cleanPath = $path;
        $query = '';
        $qPos = strpos($path, '?');
        if ($qPos !== false) {
            $cleanPath = substr($path, 0, $qPos);
            $query = substr($path, $qPos);
        }

        $result[] = $path;
        $dir = dirname($cleanPath);
        $base = basename($cleanPath);

        if (preg_match('/^(.+?)([_-](?:thumb|thumbnail))(\.[a-z0-9]+)$/i', $base, $m)) {
            $stem = $m[1];
            $ext = $m[3];
            $result[] = $dir . '/' . $stem . $ext . $query;
            $result[] = $dir . '/' . $stem . '1' . $ext . $query;
        }

        return array_values(array_unique($result));
    }

    private static function fileExistsByPublicPath(string $publicPath): bool
    {
        $path = trim($publicPath);
        if ($path === '') {
            return false;
        }

        if (preg_match('#^https?://#i', $path)) {
            $parts = parse_url($path);
            $path = $parts['path'] ?? '';
            if ($path === '') {
                return false;
            }
        }

        if (!str_starts_with($path, '/')) {
            $path = '/' . ltrim($path, '/');
        }

        if (!str_starts_with($path, '/storage/')) {
            return false;
        }

        $fs = rtrim(APP_ROOT, '/') . $path;
        return is_file($fs);
    }

    private static function buildGrid(array $items): string
    {
        $html = '<div class="gallery-grid" data-gallery>';
        foreach ($items as $i => $item) {
            $full    = htmlspecialchars($item['full'],    ENT_QUOTES, 'UTF-8');
            $thumb   = htmlspecialchars($item['thumb'],   ENT_QUOTES, 'UTF-8');
            $caption = htmlspecialchars($item['caption'], ENT_QUOTES, 'UTF-8');
            $html .= <<<ITEM
<a href="{$full}" class="gallery-item" data-index="{$i}" data-description="{$caption}">
    <img src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==" data-src="{$thumb}" alt="{$caption}" loading="lazy" decoding="async" fetchpriority="low" class="gallery-thumb-lazy">
    <div class="gallery-item-overlay">
        <svg class="gallery-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/>
        </svg>
    </div>
</a>
ITEM;
        }
        $html .= '</div>';
        return $html;
    }

    private static function buildLightbox(): string
    {
        return <<<HTML
<div class="gallery-lightbox" id="galleryLightbox" role="dialog" aria-modal="true" aria-label="Просмотр изображения">
    <div class="gallery-lightbox-overlay"></div>
    <div class="gallery-lightbox-container">
        <button class="gallery-close" aria-label="Закрыть">&times;</button>
        <button class="gallery-prev" aria-label="Предыдущее">&#8249;</button>
        <button class="gallery-next" aria-label="Следующее">&#8250;</button>
        <div class="gallery-lightbox-content">
            <img id="galleryLightboxImage" src="" alt="">
            <div id="galleryLightboxCaption" class="gallery-lightbox-caption"></div>
        </div>
        <div id="galleryCounter" class="gallery-counter"></div>
    </div>
</div>
HTML;
    }
}
