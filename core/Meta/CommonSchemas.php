<?php
namespace Core\Meta;

/**
 * Common Schema.org structured data templates.
 * All methods return arrays ready for JSON encoding.
 */
class CommonSchemas
{
    /**
     * Schema.org Organization
     *
     * @param array $data - ['name', 'url', 'logo' (optional)]
     * @return array
     */
    public static function organization(array $data): array
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $data['name'] ?? '',
            'url' => $data['url'] ?? '',
        ];

        // Add logo only if provided
        if (!empty($data['logo'])) {
            $schema['logo'] = $data['logo'];
        }

        return $schema;
    }

    /**
     * Schema.org WebSite
     *
     * @param array $data - ['name', 'url']
     * @return array
     */
    public static function webSite(array $data): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $data['name'] ?? '',
            'url' => $data['url'] ?? '',
        ];
    }

    /**
     * Schema.org BreadcrumbList
     *
     * @param array $items - [['name' => '', 'url' => ''], ...]
     * @return array
     */
    public static function breadcrumbList(array $items): array
    {
        $listItems = [];
        $position = 1;

        foreach ($items as $item) {
            $listItem = [
                '@type' => 'ListItem',
                'position' => $position,
                'name' => $item['name'] ?? '',
            ];

            // Add 'item' URL only if provided
            if (!empty($item['url'])) {
                $listItem['item'] = $item['url'];
            }

            $listItems[] = $listItem;
            $position++;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $listItems,
        ];
    }
}
