<?php

namespace OnikImages\Css;

/**
 * Finds background-image declarations in CSS text.
 *
 * This is a forward tokenizer, not a full parser and not backward-walking
 * regex. Real stylesheets arrive minified with no whitespace to anchor on, so
 * "find url() then scan backwards for a selector" is unreliable. Tracking brace
 * depth, paren depth, strings and at-rule context in one pass is both shorter
 * and correct.
 *
 * What it deliberately does NOT do: resolve the cascade, compute specificity,
 * or decide whether a rule is worth optimizing. It reports what is there.
 */
class CssScanner
{
    /**
     * At-rules whose braces contain further rules rather than declarations.
     * Anything else with a brace block (@font-face, @keyframes, @page,
     * @property) holds declarations we have no selector for, so its contents
     * are skipped.
     */
    private const CONDITIONAL_AT_RULES = [
        'media',
        'supports',
        'layer',
        'container',
        'document',
        'scope',
    ];

    private const BACKGROUND_PROPERTIES = ['background', 'background-image'];

    /**
     * One definition of what a url() token looks like, shared by extraction and
     * rewriting so the two can never disagree about what they matched.
     */
    private const URL_PATTERN = '/\burl\(\s*(?:"([^"]*)"|\'([^\']*)\'|([^)]*?))\s*\)/i';

    /**
     * @return array<int, array{
     *   source: string,
     *   atRules: array<int, string>,
     *   selector: string,
     *   selectors: array<int, string>,
     *   property: string,
     *   value: string,
     *   important: bool,
     *   urls: array<int, array{raw: string, resolved: ?string, skip: ?string}>
     * }>
     */
    public static function scan(string $css, string $sourceUrl = ''): array
    {
        $css     = self::stripComments($css);
        $len     = strlen($css);
        $records = [];
        $stack   = [];
        $buf     = '';
        $paren   = 0;
        $i       = 0;

        while ($i < $len) {
            $ch = $css[$i];

            if ($ch === '"' || $ch === "'") {
                $end  = self::readString($css, $i);
                $buf .= substr($css, $i, $end - $i);
                $i    = $end;
                continue;
            }

            if ($ch === '(') {
                $paren++;
                $buf .= $ch;
                $i++;
                continue;
            }

            if ($ch === ')') {
                if ($paren > 0) {
                    $paren--;
                }
                $buf .= $ch;
                $i++;
                continue;
            }

            // Inside url(...) or a gradient argument list, structural
            // characters are just text.
            if ($paren > 0) {
                $buf .= $ch;
                $i++;
                continue;
            }

            if ($ch === '{') {
                $stack[] = self::openFrame(trim($buf), $stack);
                $buf     = '';
                $i++;
                continue;
            }

            if ($ch === '}') {
                // A final declaration with no trailing semicolon is legal.
                self::flushDeclaration($buf, $stack, $sourceUrl, $records);
                array_pop($stack);
                $buf = '';
                $i++;
                continue;
            }

            if ($ch === ';') {
                // Outside a block this is a statement at-rule (@import,
                // @charset) which we have nothing to do with.
                self::flushDeclaration($buf, $stack, $sourceUrl, $records);
                $buf = '';
                $i++;
                continue;
            }

            $buf .= $ch;
            $i++;
        }

        return $records;
    }

    /**
     * @param array<int, array<string, mixed>> $stack
     * @return array{type: string, prelude: string, selector: string}
     */
    private static function openFrame(string $prelude, array $stack): array
    {
        $parent = end($stack) ?: null;

        // Anything nested inside a block we already gave up on stays skipped,
        // e.g. the `0% { }` steps inside @keyframes.
        if ($parent !== null && $parent['type'] === 'opaque') {
            return ['type' => 'opaque', 'prelude' => $prelude, 'selector' => ''];
        }

        if ($prelude !== '' && $prelude[0] === '@') {
            $name = strtolower(substr($prelude, 1, strcspn($prelude, " \t\n\r({", 1)));
            $type = in_array($name, self::CONDITIONAL_AT_RULES, true) ? 'at' : 'opaque';
            return ['type' => $type, 'prelude' => $prelude, 'selector' => ''];
        }

        // CSS nesting. Astra does not emit it, but a hand-written stylesheet
        // might, and silently attributing a nested rule's background to the
        // parent selector would be worse than handling it.
        $selector = $prelude;
        if ($parent !== null && $parent['type'] === 'rule' && $parent['selector'] !== '') {
            $selector = strpos($prelude, '&') !== false
                ? str_replace('&', $parent['selector'], $prelude)
                : $parent['selector'] . ' ' . $prelude;
        }

        return ['type' => 'rule', 'prelude' => $prelude, 'selector' => $selector];
    }

    /**
     * @param array<int, array<string, mixed>> $stack
     * @param array<int, array<string, mixed>> $records
     */
    private static function flushDeclaration(string $buf, array $stack, string $sourceUrl, array &$records): void
    {
        $frame = end($stack) ?: null;
        if ($frame === null || $frame['type'] !== 'rule') {
            return;
        }

        $declaration = trim($buf);
        if ($declaration === '') {
            return;
        }

        $colon = self::topLevelIndexOf($declaration, ':');
        if ($colon === null) {
            return;
        }

        $property = strtolower(trim(substr($declaration, 0, $colon)));
        if (!in_array($property, self::BACKGROUND_PROPERTIES, true)) {
            return;
        }

        $value = trim(substr($declaration, $colon + 1));
        if (stripos($value, 'url(') === false) {
            return;
        }

        // Carry !important through. An override that drops it loses to the
        // declaration it was meant to replace.
        $important = false;
        if (preg_match('/!\s*important\s*$/i', $value)) {
            $important = true;
            $value     = trim(preg_replace('/!\s*important\s*$/i', '', $value));
        }

        $urls = self::extractUrls($value, $sourceUrl);
        if ($urls === []) {
            return;
        }

        $atRules = [];
        foreach ($stack as $entry) {
            if ($entry['type'] === 'at') {
                $atRules[] = $entry['prelude'];
            }
        }

        $records[] = [
            'source'    => $sourceUrl,
            'atRules'   => $atRules,
            'selector'  => $frame['selector'],
            'selectors' => self::splitSelectorList($frame['selector']),
            'property'  => $property,
            'value'     => $value,
            'important' => $important,
            'urls'      => $urls,
        ];
    }

    /**
     * Every url() token in a declaration value, resolved against the
     * stylesheet. Multi-layer backgrounds are normal: Astra's footer builder
     * emits `linear-gradient(...),url(...)`, so a value can hold several.
     *
     * @return array<int, array{raw: string, resolved: ?string, skip: ?string}>
     */
    public static function extractUrls(string $value, string $sourceUrl = ''): array
    {
        if (!preg_match_all(self::URL_PATTERN, $value, $matches, PREG_SET_ORDER)) {
            return [];
        }

        $urls = [];
        foreach ($matches as $match) {
            $raw      = self::rawFromMatch($match);
            $resolved = UrlResolver::resolve($raw, $sourceUrl);
            $urls[]   = [
                'raw'      => $raw,
                'resolved' => $resolved['url'],
                'skip'     => $resolved['skip'],
            ];
        }

        return $urls;
    }

    /**
     * Rewrite the url() tokens in a declaration value, leaving every other byte
     * of it alone.
     *
     * Rebuilding the value from parts would lose gradient layers, positions and
     * sizes that share the declaration, so the original string is edited in
     * place instead. $map receives the resolved absolute URL and returns a
     * replacement, or null to leave that url() untouched.
     */
    public static function rewriteUrls(string $value, string $sourceUrl, callable $map, int &$changed = 0): string
    {
        $changed = 0;

        $result = preg_replace_callback(
            self::URL_PATTERN,
            function ($match) use ($sourceUrl, $map, &$changed) {
                $raw      = self::rawFromMatch($match);
                $resolved = UrlResolver::resolve($raw, $sourceUrl);

                if ($resolved['url'] === null || $resolved['skip'] !== null) {
                    return $match[0];
                }

                $replacement = $map($resolved['url']);
                if (!is_string($replacement) || $replacement === '') {
                    return $match[0];
                }

                $changed++;

                // Always quote the replacement. Converter URLs carry a query
                // string, and an unquoted url() with & and = in it is asking
                // for trouble across parsers.
                return 'url("' . $replacement . '")';
            },
            $value
        );

        return is_string($result) ? $result : $value;
    }

    /**
     * The url() token text, from whichever of the three quoting alternatives
     * the pattern matched.
     *
     * @param array<int, string> $match
     */
    private static function rawFromMatch(array $match): string
    {
        if (isset($match[1]) && $match[1] !== '') {
            return $match[1];
        }
        if (isset($match[2]) && $match[2] !== '') {
            return $match[2];
        }

        return $match[3] ?? '';
    }

    /**
     * Split a selector list on top-level commas, leaving :is(a, b) and
     * [attr="a,b"] alone.
     *
     * @return array<int, string>
     */
    public static function splitSelectorList(string $selector): array
    {
        $out   = [];
        $buf   = '';
        $paren = 0;
        $len   = strlen($selector);
        $i     = 0;

        while ($i < $len) {
            $ch = $selector[$i];

            if ($ch === '"' || $ch === "'") {
                $end  = self::readString($selector, $i);
                $buf .= substr($selector, $i, $end - $i);
                $i    = $end;
                continue;
            }
            if ($ch === '(' || $ch === '[') {
                $paren++;
            } elseif ($ch === ')' || $ch === ']') {
                if ($paren > 0) {
                    $paren--;
                }
            } elseif ($ch === ',' && $paren === 0) {
                $out[] = trim($buf);
                $buf   = '';
                $i++;
                continue;
            }

            $buf .= $ch;
            $i++;
        }

        if (trim($buf) !== '') {
            $out[] = trim($buf);
        }

        return array_values(array_filter($out, function ($s) {
            return $s !== '';
        }));
    }

    /**
     * Index of the first $needle that is not inside parens, brackets or a
     * string. Used to split `prop: value` without tripping on a pseudo-class
     * inside :is() or a colon inside a data: URI.
     */
    private static function topLevelIndexOf(string $haystack, string $needle): ?int
    {
        $depth = 0;
        $len   = strlen($haystack);
        $i     = 0;

        while ($i < $len) {
            $ch = $haystack[$i];

            if ($ch === '"' || $ch === "'") {
                $i = self::readString($haystack, $i);
                continue;
            }
            if ($ch === '(' || $ch === '[') {
                $depth++;
            } elseif ($ch === ')' || $ch === ']') {
                if ($depth > 0) {
                    $depth--;
                }
            } elseif ($ch === $needle && $depth === 0) {
                return $i;
            }

            $i++;
        }

        return null;
    }

    /**
     * Remove /* *​/ comments without touching comment-like text inside strings.
     */
    private static function stripComments(string $css): string
    {
        $out = '';
        $len = strlen($css);
        $i   = 0;

        while ($i < $len) {
            $ch = $css[$i];

            if ($ch === '"' || $ch === "'") {
                $end  = self::readString($css, $i);
                $out .= substr($css, $i, $end - $i);
                $i    = $end;
                continue;
            }

            if ($ch === '/' && $i + 1 < $len && $css[$i + 1] === '*') {
                $close = strpos($css, '*/', $i + 2);
                $i     = $close === false ? $len : $close + 2;
                // Keep a separator so `a/*x*/b` does not become `ab`.
                $out  .= ' ';
                continue;
            }

            $out .= $ch;
            $i++;
        }

        return $out;
    }

    /**
     * Index just past the closing quote of the string starting at $start.
     */
    private static function readString(string $s, int $start): int
    {
        $quote = $s[$start];
        $len   = strlen($s);
        $i     = $start + 1;

        while ($i < $len) {
            if ($s[$i] === '\\') {
                $i += 2;
                continue;
            }
            if ($s[$i] === $quote) {
                return $i + 1;
            }
            $i++;
        }

        return $len;
    }
}
