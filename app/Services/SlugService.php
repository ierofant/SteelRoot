<?php
declare(strict_types=1);

namespace App\Services;

final class SlugService
{
    private const CYRILLIC_MAP = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd',
        'е' => 'e', 'ё' => 'yo', 'ж' => 'zh', 'з' => 'z', 'и' => 'i',
        'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n',
        'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
        'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'ts', 'ч' => 'ch',
        'ш' => 'sh', 'щ' => 'sch', 'ъ' => '', 'ы' => 'y', 'ь' => '',
        'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
    ];

    public static function slugify(string $value, string $fallback = 'item'): string
    {
        $value = trim(strip_tags($value));
        if ($value === '') {
            return $fallback;
        }

        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = mb_strtolower($value, 'UTF-8');
        $value = strtr($value, self::CYRILLIC_MAP);

        $iconv = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($iconv) && $iconv !== '') {
            $value = $iconv;
        }

        $value = preg_replace('/[^a-z0-9]+/i', '-', $value) ?? '';
        $value = trim($value, '-');

        return $value !== '' ? strtolower($value) : $fallback;
    }

    public static function candidates(string $rawSlug): array
    {
        $rawSlug = trim($rawSlug);
        if ($rawSlug === '') {
            return [];
        }

        $decoded = rawurldecode($rawSlug);
        $variants = [
            $rawSlug,
            $decoded,
            mb_strtolower($decoded, 'UTF-8'),
            self::slugify($decoded, ''),
            self::slugify($rawSlug, ''),
        ];

        $result = [];
        foreach ($variants as $variant) {
            $variant = trim((string)$variant);
            if ($variant !== '' && !in_array($variant, $result, true)) {
                $result[] = $variant;
            }
        }

        return $result;
    }
}
