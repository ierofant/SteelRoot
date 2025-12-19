<?php
namespace Core\Meta;

final class MetaResolver
{
    /**
     * Meta Contract v1: content > menu > defaults.
     */
    public static function resolve(array $baseMeta, array $contentMeta, array $menuMeta): array
    {
        $base = [
            'title' => $baseMeta['title'] ?? 'SteelRoot',
            'description' => $baseMeta['description'] ?? '',
            'canonical' => $baseMeta['canonical'] ?? null,
            'image' => $baseMeta['image'] ?? null,
        ];
        $content = [
            'title' => $contentMeta['title'] ?? null,
            'description' => $contentMeta['description'] ?? null,
            'canonical' => $contentMeta['canonical'] ?? null,
            'image' => $contentMeta['image'] ?? null,
        ];
        $menu = [
            'title' => $menuMeta['title'] ?? null,
            'description' => $menuMeta['description'] ?? null,
            'canonical' => $menuMeta['canonical'] ?? null,
            'image' => $menuMeta['image'] ?? null,
        ];

        $result = $base;
        foreach (['title', 'description', 'canonical', 'image'] as $key) {
            if (!empty($content[$key])) {
                $result[$key] = $content[$key];
                continue;
            }
            if (!empty($menu[$key])) {
                $result[$key] = $menu[$key];
            }
        }
        return $result;
    }
}
