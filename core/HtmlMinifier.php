<?php
namespace Core;

class HtmlMinifier
{
    public static function minify(string $html): string
    {
        $preserved = [];
        $i = 0;

        // Preserve <pre>, <textarea>, <script>, <style> content as-is
        $html = preg_replace_callback(
            '/<(pre|textarea|script|style)\b[^>]*>[\s\S]*?<\/\1>/i',
            static function (array $m) use (&$preserved, &$i): string {
                $key = "\x02P{$i}\x03";
                $preserved[$key] = $m[0];
                $i++;
                return $key;
            },
            $html
        );

        // Remove HTML comments (keep IE conditionals)
        $html = preg_replace('/<!--(?!\[if\s)[\s\S]*?-->/i', '', $html);

        // Collapse runs of whitespace (spaces, tabs, newlines) into single space
        $html = preg_replace('/\s{2,}/', ' ', $html);

        // Remove spaces between tags
        $html = preg_replace('/>\s+</', '><', $html);

        // Restore preserved blocks
        foreach ($preserved as $key => $block) {
            $html = str_replace($key, $block, $html);
        }

        return trim($html);
    }
}
