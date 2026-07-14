<?php

namespace OnikImages\Rewrite\Collectors;

/**
 * Class facade over <div>/<span> background-image rewrites in
 * src/Rewrite/Collectors/div-collector.php.
 */
class DivCollector
{
    public static function collect($divTag, string $location, string $selector, array $config, string $originalHtml): array
    {
        return collectDivModifications($divTag, $location, $selector, $config, $originalHtml);
    }

    public static function applyConfigToStyleTag($divTag, string $location, array $config): void
    {
        applyConfigToDivStyleTag($divTag, $location, $config);
    }

    public static function applyConfigToDataSettings($divTag, string $location, array $config): void
    {
        applyConfigToDivDataSettings($divTag, $location, $config);
    }

    public static function findOriginalHtml($divTag, string $originalHtml): ?string
    {
        return findOriginalDivHtml($divTag, $originalHtml);
    }

    public static function findCompleteDivWithNestedContent(string $html, int $openingTagStart): ?string
    {
        return findCompleteDivWithNestedContent($html, $openingTagStart);
    }
}
