<?php

namespace OnikImages\Css;

/**
 * Builds the <style> block that redirects stylesheet background images at the
 * ONIK converter.
 *
 * The stylesheet itself is never touched. Instead the same rule is re-emitted
 * later in the head with a converter URL, and the cascade picks the winner.
 * That is not a trick to save effort: a CSS background image is only fetched
 * once the cascade resolves and a matching element renders, so the losing
 * declaration's URL is never requested. Rewriting the file, or preloading the
 * converted URL alongside it, would both cost two downloads.
 */
class OverrideBuilder
{
    /**
     * Stylesheets examined per request. A page linking more than this is
     * unusual; the cap stops a pathological page from doing unbounded file IO
     * on a cold cache. When it bites, the debug comment says so rather than
     * silently reporting full coverage.
     */
    private const MAX_STYLESHEETS = 40;

    private const STYLE_ID = 'onik-lens-css';

    /**
     * @param array<string, array<string, mixed>> $selectorWidthMapping
     * @param array<string, mixed>|null $stats Filled in for the debug comment.
     */
    public static function build(
        \DOMDocument $dom,
        array $selectorWidthMapping,
        string $appendLocation,
        ?array &$stats = null
    ): string {
        $stats = [
            'targets'     => 0,
            'stylesheets' => 0,
            'skipped'     => 0,
            'capped'      => false,
            'rules'       => 0,
        ];

        $targets = self::targetSelectors($selectorWidthMapping);
        $stats['targets'] = count($targets);
        if ($targets === []) {
            return '';
        }

        $hrefs = self::stylesheetHrefs($dom);
        if (count($hrefs) > self::MAX_STYLESHEETS) {
            $stats['capped'] = true;
            $stats['skipped'] = count($hrefs) - self::MAX_STYLESHEETS;
            $hrefs = array_slice($hrefs, 0, self::MAX_STYLESHEETS);
        }

        $rules = [];

        foreach ($hrefs as $href) {
            $sheet = StylesheetReader::read($href);
            if (!$sheet['ok']) {
                continue;
            }
            $stats['stylesheets']++;

            foreach ($sheet['records'] as $record) {
                foreach (self::matchingSelectors($record, $targets) as $selector) {
                    $rule = self::buildRule($record, $selector, $targets[$selector], $appendLocation);
                    if ($rule !== null) {
                        $rules[] = $rule;
                    }
                }
            }
        }

        $rules = array_values(array_unique($rules));
        $stats['rules'] = count($rules);

        if ($rules === []) {
            return '';
        }

        return '<style id="' . self::STYLE_ID . '">' . implode('', $rules) . '</style>';
    }

    /**
     * Configured selectors in ExternalCssUrl mode. Everything else in the
     * settings map belongs to a different collector.
     *
     * @param array<string, array<string, mixed>> $selectorWidthMapping
     * @return array<string, array<string, mixed>>
     */
    private static function targetSelectors(array $selectorWidthMapping): array
    {
        $targets = [];

        foreach ($selectorWidthMapping as $selector => $config) {
            if (!is_array($config)) {
                continue;
            }
            if (($config['srcSwap'] ?? '') !== 'ExternalCssUrl') {
                continue;
            }
            // The settings JSON is hand-editable, so re-check the selector here
            // rather than trusting whatever reached the option.
            if (Gate::sanitizeSelector((string) $selector) === '') {
                continue;
            }

            $targets[$selector] = $config;
        }

        return $targets;
    }

    /**
     * Which configured selectors this record satisfies.
     *
     * A configured `.hero` matches a source rule written `.hero,.banner{...}`,
     * and the override is emitted for `.hero` alone. Narrowing is deliberate:
     * `.banner` was never asked for and keeps its original image.
     *
     * @param array<string, mixed> $record
     * @param array<string, array<string, mixed>> $targets
     * @return array<int, string>
     */
    private static function matchingSelectors(array $record, array $targets): array
    {
        $matched = [];

        foreach ($targets as $selector => $config) {
            if ($selector === $record['selector'] || in_array($selector, $record['selectors'], true)) {
                $matched[] = $selector;
            }
        }

        return $matched;
    }

    /**
     * @param array<string, mixed> $record
     * @param array<string, mixed> $config
     */
    private static function buildRule(array $record, string $selector, array $config, string $appendLocation): ?string
    {
        $quality = isset($config['quality']) ? (int) $config['quality'] : 80;
        $format  = isset($config['format']) ? (string) $config['format'] : 'auto';
        $width   = '';
        if (isset($config['widths']) && is_array($config['widths']) && $config['widths'] !== []) {
            $width = (string) $config['widths'][0];
        }

        $changed  = 0;
        $newValue = CssScanner::rewriteUrls(
            $record['value'],
            $record['source'],
            function (string $url) use ($appendLocation, $quality, $format, $width) {
                if (!should_alter_image_based_on_src($url)) {
                    return null;
                }

                $converted = $appendLocation . rawurlencode($url) . '?quality=' . $quality;
                if ($format !== '') {
                    $converted .= '&format=' . $format;
                }
                if ($width !== '') {
                    $converted .= '&width=' . $width;
                }

                return $converted;
            },
            $changed
        );

        if ($changed === 0) {
            return null;
        }

        $rule = $selector . '{' . $record['property'] . ':' . $newValue
            . ($record['important'] ? ' !important' : '') . '}';

        // Wrap back up in the at-rule context, innermost first.
        foreach (array_reverse($record['atRules']) as $atRule) {
            $rule = $atRule . '{' . $rule . '}';
        }

        // Nothing built from file contents gets to close the <style> element.
        if (stripos($rule, '</') !== false) {
            return null;
        }

        return $rule;
    }

    /**
     * Stylesheet URLs the page links, in document order, deduplicated.
     *
     * Read from the DOM rather than wp_styles() because this is literally what
     * the browser will fetch, including sheets printed outside the enqueue
     * system.
     *
     * @return array<int, string>
     */
    public static function stylesheetHrefs(\DOMDocument $dom): array
    {
        $hrefs = [];

        foreach ($dom->getElementsByTagName('link') as $link) {
            $rel = strtolower(trim($link->getAttribute('rel')));
            if ($rel === '' || !in_array('stylesheet', preg_split('/\s+/', $rel), true)) {
                continue;
            }

            $href = trim($link->getAttribute('href'));
            if ($href === '') {
                continue;
            }

            $absolute = self::absoluteHref($href);
            if ($absolute !== null && !in_array($absolute, $hrefs, true)) {
                $hrefs[] = $absolute;
            }
        }

        return $hrefs;
    }

    /**
     * WordPress emits absolute stylesheet URLs, but a theme or plugin can print
     * a root-relative or protocol-relative one by hand.
     */
    private static function absoluteHref(string $href): ?string
    {
        if (preg_match('#^[a-z][a-z0-9+.\-]*://#i', $href)) {
            return $href;
        }

        $base = function_exists('home_url') ? home_url('/') : '';
        if ($base === '') {
            return null;
        }

        return UrlResolver::resolve($href, $base)['url'];
    }
}
