<?php

namespace OnikImages\Rewrite\Collectors;

/**
 * Class facade over inline <style> block rewrites in
 * src/Rewrite/Collectors/inline-style-collector.php.
 */
class InlineStyleCollector
{
    public static function collect($dom, array $selectorWidthMapping, string $location, string $html): array
    {
        return collectInlineStyleBlockModifications($dom, $selectorWidthMapping, $location, $html);
    }
}
