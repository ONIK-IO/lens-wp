<?php

namespace OnikImages\Rewrite\Collectors;

/**
 * Class facade over regex-based document-wide rewrites in
 * src/Rewrite/Collectors/regex-collector.php.
 */
class RegexCollector
{
    public static function collect(string $originalHtml, string $location, array $regexConfigs): array
    {
        return collectRegexModifications($originalHtml, $location, $regexConfigs);
    }
}
