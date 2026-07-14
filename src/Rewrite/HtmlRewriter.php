<?php

namespace OnikImages\Rewrite;

/**
 * Class facade over alter_html / alter_html_hybrid in src/Rewrite/rewriter.php.
 *
 * The procedural function name is what ob_start() registers as the buffer
 * callback (`ob_start('alter_html')`), so it must remain in the global scope.
 * Tests and new code should prefer this OO surface.
 */
class HtmlRewriter
{
    public static function rewrite(string $html, ?string $currentPathOverride = null): string
    {
        return alter_html($html, $currentPathOverride);
    }

    public static function rewriteHybrid(string $html, ?string $currentPathOverride = null): string
    {
        return alter_html_hybrid($html, $currentPathOverride);
    }

    public static function buildPreloadLinkTags($dom, array $preloads, ?string $currentPathOverride = null): string
    {
        return buildPreloadLinkTags($dom, $preloads, $currentPathOverride);
    }
}
