<?php
namespace Modules\Articles\Providers;

use DateTime;
use DateTimeZone;

/**
 * Generates Schema.org Article structured data.
 *
 * Usage:
 *   $provider = new ArticleSchemaProvider($baseUrl);
 *   $schema   = $provider->getSchema($articleRow, $options);
 *
 * $options keys:
 *   locale     string       'ru'|'en'
 *   canonical  string       Absolute URL of the article page
 *   ogImg      string|null  Absolute URL of the OG/cover image
 *   desc       string       Short text description
 *   tags       array        [['name'=>'...'], ...]
 *   publicBase string       e.g. '/articles' or '/news'
 *   org        array        ['name'=>'', 'url'=>'', 'logo'=>'']
 */
class ArticleSchemaProvider
{
    private string $baseUrl;
    protected string $schemaType = 'Article';

    public function __construct(string $baseUrl)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function getSchema(array $article, array $options = []): array
    {
        $locale    = $options['locale'] ?? 'en';
        $titleKey  = $locale === 'ru' ? 'title_ru' : 'title_en';
        $title     = (string)($article[$titleKey] ?? ($article['title_en'] ?? ($article['title_ru'] ?? '')));
        $canonical = $options['canonical']
            ?? ($this->baseUrl . ($options['publicBase'] ?? '/articles') . '/' . ($article['slug'] ?? ''));

        $schema = [
            '@context'   => 'https://schema.org',
            '@type'      => $this->schemaType,
            '@id'        => $canonical,
            'headline'   => $title,
            'url'        => $canonical,
            'inLanguage' => $locale === 'ru' ? 'ru-RU' : 'en-US',
        ];

        if (!empty($article['created_at'])) {
            $schema['datePublished'] = $this->isoDate($article['created_at']);
        }
        if (!empty($article['updated_at'])) {
            $schema['dateModified'] = $this->isoDate($article['updated_at']);
        }

        $desc = trim((string)($options['desc'] ?? ''));
        if ($desc !== '') {
            $schema['description'] = $desc;
        }

        // Image as ImageObject
        $img = $options['ogImg'] ?? ($article['image_url'] ?? null);
        if ($img) {
            $schema['image'] = ['@type' => 'ImageObject', 'url' => (string)$img];
        }

        // Author as Person
        $authorName = trim((string)($article['author_name'] ?? ''));
        if ($authorName !== '') {
            $author = ['@type' => 'Person', 'name' => $authorName];
            if (!empty($article['author_username'])) {
                $author['url'] = $this->baseUrl . '/users/' . rawurlencode((string)$article['author_username']);
            } elseif (!empty($article['author_id'])) {
                $author['url'] = $this->baseUrl . '/users/' . (int)$article['author_id'];
            }
            if (!empty($article['author_avatar'])) {
                $av = (string)$article['author_avatar'];
                $author['image'] = str_starts_with($av, 'http') ? $av : $this->baseUrl . '/' . ltrim($av, '/');
            }
            $schema['author'] = $author;
        }

        // Publisher as Organization (embedded, not a separate graph node)
        $org = $options['org'] ?? [];
        if (!empty($org['name'])) {
            $publisher = ['@type' => 'Organization', 'name' => (string)$org['name']];
            if (!empty($org['url']))  $publisher['url']  = (string)$org['url'];
            if (!empty($org['logo'])) $publisher['logo'] = ['@type' => 'ImageObject', 'url' => (string)$org['logo']];
            $schema['publisher'] = $publisher;
        }

        // Keywords from tags
        $tags = $options['tags'] ?? [];
        if (!empty($tags)) {
            $kw = array_values(array_filter(array_map(fn($t) => trim((string)($t['name'] ?? '')), $tags)));
            if (!empty($kw)) {
                $schema['keywords'] = implode(', ', $kw);
            }
        }

        // articleSection from category
        $catKey      = $locale === 'ru' ? 'category_name_ru' : 'category_name_en';
        $catFallback = $locale === 'ru' ? 'category_name_en' : 'category_name_ru';
        $section = trim((string)($article[$catKey] ?? ($article[$catFallback] ?? '')));
        if ($section !== '') {
            $schema['articleSection'] = $section;
        }

        // ReadAction interaction counter
        $views = (int)($article['views'] ?? 0);
        if ($views > 0) {
            $schema['interactionStatistic'] = [
                '@type'                => 'InteractionCounter',
                'interactionType'      => 'https://schema.org/ReadAction',
                'userInteractionCount' => $views,
            ];
        }

        return $schema;
    }

    private function isoDate(string $value): string
    {
        try {
            $d = new DateTime($value);
            $d->setTimezone(new DateTimeZone('UTC'));
            return $d->format('c');
        } catch (\Exception $e) {
            return $value;
        }
    }
}
