<?php
namespace Core\Meta;

/**
 * Renders JSON-LD structured data for Schema.org markup.
 * Provides methods to render single schemas or merge multiple schemas via @graph.
 */
class JsonLdRenderer
{
    /**
     * Renders array as <script type="application/ld+json"> tag
     *
     * @param array $schema - JSON-LD data array
     * @return string - HTML script tag with escaped JSON
     */
    public static function render(array $schema): string
    {
        if (empty($schema)) {
            return '';
        }

        $json = json_encode(
            $schema,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
        );

        if ($json === false) {
            return '';
        }

        // Escape closing script tag to prevent XSS
        $json = str_replace('</', '<\/', $json);

        return $json;
    }

    /**
     * Merges multiple schemas using @graph structure
     *
     * @param array ...$schemas - Multiple schema arrays
     * @return array - Merged schema with @graph or single schema
     */
    public static function merge(array ...$schemas): array
    {
        // Filter out empty schemas
        $schemas = array_filter($schemas, fn($s) => !empty($s));

        if (empty($schemas)) {
            return [];
        }

        // Single schema - return as-is
        if (count($schemas) === 1) {
            return reset($schemas);
        }

        // Multiple schemas - wrap in @graph
        return [
            '@context' => 'https://schema.org',
            '@graph' => array_values($schemas)
        ];
    }
}
