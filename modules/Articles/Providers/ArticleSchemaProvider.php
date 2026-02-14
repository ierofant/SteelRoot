<?php
namespace Modules\Articles\Providers;

use DateTime;
use DateTimeZone;

/**
 * Generates Schema.org Article structured data for articles.
 */
class ArticleSchemaProvider
{
    private string $baseUrl;

    public function __construct(string $baseUrl)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * Generates Schema.org Article schema
     *
     * @param array $article - Article data array with fields:
     *                         title_en, slug, created_at, updated_at,
     *                         preview_en (optional), image_url (optional)
     * @return array
     */
    public function getSchema(array $article): array
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $article['title_en'] ?? '',
            'url' => $this->baseUrl . '/articles/' . ($article['slug'] ?? ''),
        ];

        // Add published date in ISO 8601 format
        if (!empty($article['created_at'])) {
            $schema['datePublished'] = $this->formatDate($article['created_at']);
        }

        // Add modified date in ISO 8601 format
        if (!empty($article['updated_at'])) {
            $schema['dateModified'] = $this->formatDate($article['updated_at']);
        }

        // Add description if preview exists
        if (!empty($article['preview_en'])) {
            $schema['description'] = $article['preview_en'];
        }

        // Add image only if provided
        if (!empty($article['image_url'])) {
            $schema['image'] = $article['image_url'];
        }

        return $schema;
    }

    /**
     * Formats date string to ISO 8601 format
     *
     * @param string $dateString
     * @return string
     */
    private function formatDate(string $dateString): string
    {
        try {
            $date = new DateTime($dateString);
            // Ensure UTC timezone for consistency
            $date->setTimezone(new DateTimeZone('UTC'));
            return $date->format('c'); // ISO 8601 format
        } catch (\Exception $e) {
            // Fallback to original string if parsing fails
            return $dateString;
        }
    }
}
