<?php

namespace OnikImages\Css;

/**
 * Resolves url() references found inside a stylesheet, and maps stylesheet
 * URLs back to local filesystem paths.
 *
 * The important rule: a url() in CSS resolves against the STYLESHEET's URL,
 * not the page URL. `url(../images/hero.jpg)` inside
 * /wp-content/themes/x/css/style.css means /wp-content/themes/x/images/hero.jpg.
 * Resolving it against the page instead produces plausible-looking 404s.
 */
class UrlResolver
{
    public const SKIP_DATA_URI = 'data-uri';
    public const SKIP_FRAGMENT = 'fragment';
    public const SKIP_EMPTY    = 'empty';

    /**
     * Resolve a raw url() token against the stylesheet it was found in.
     *
     * @return array{url: ?string, skip: ?string}
     */
    public static function resolve(string $raw, string $baseUrl): array
    {
        $raw = trim($raw);

        if ($raw === '') {
            return ['url' => null, 'skip' => self::SKIP_EMPTY];
        }
        if (stripos($raw, 'data:') === 0) {
            return ['url' => null, 'skip' => self::SKIP_DATA_URI];
        }
        // In-document references such as url(#ast-img-color-filter-3), used for
        // SVG filters. Astra emits these, so this branch is load-bearing.
        if ($raw[0] === '#') {
            return ['url' => null, 'skip' => self::SKIP_FRAGMENT];
        }

        // Already absolute.
        if (preg_match('#^[a-z][a-z0-9+.\-]*://#i', $raw)) {
            return ['url' => $raw, 'skip' => null];
        }

        $base = self::parseBase($baseUrl);
        if ($base === null) {
            return ['url' => null, 'skip' => null];
        }

        // Protocol-relative: //cdn.example.com/x.jpg
        if (strpos($raw, '//') === 0) {
            return ['url' => $base['scheme'] . ':' . $raw, 'skip' => null];
        }

        // Root-relative: /wp-content/uploads/x.jpg
        if ($raw[0] === '/') {
            return ['url' => $base['origin'] . self::normalizePath($raw), 'skip' => null];
        }

        // Document-relative, against the stylesheet's own directory.
        $dir = self::dirOf($base['path']);
        return ['url' => $base['origin'] . self::normalizePath($dir . '/' . $raw), 'skip' => null];
    }

    /**
     * Map an absolute URL on this site to a readable local file path.
     *
     * Returns null when the URL is off-site, when the file does not exist, or
     * when the resolved path escapes the directory it claimed to be under. The
     * escape check matters: the path comes from CSS text, and `../` sequences
     * in a url() must not be able to walk out of the WordPress install.
     */
    public static function toLocalPath(string $url): ?string
    {
        $url = self::stripQuery($url);

        foreach (self::baseMap() as $baseUrl => $baseDir) {
            if ($baseUrl === '' || $baseDir === '') {
                continue;
            }
            $prefix = rtrim(self::stripQuery($baseUrl), '/') . '/';
            if (strpos($url, $prefix) !== 0) {
                continue;
            }

            $relative = substr($url, strlen($prefix));
            $candidate = rtrim($baseDir, '/') . '/' . rawurldecode($relative);

            $real = @realpath($candidate);
            if ($real === false || !is_file($real)) {
                continue;
            }

            $realBase = @realpath($baseDir);
            if ($realBase === false || strpos($real, rtrim($realBase, '/') . '/') !== 0) {
                // Traversal out of the base directory. Refuse.
                continue;
            }

            return $real;
        }

        return null;
    }

    /**
     * URL prefix => filesystem directory, longest URL first so that
     * wp-content wins over the site root it sits inside.
     */
    private static function baseMap(): array
    {
        $map = [];

        if (function_exists('content_url') && defined('WP_CONTENT_DIR')) {
            $map[content_url()] = WP_CONTENT_DIR;
        }
        if (function_exists('includes_url') && defined('ABSPATH') && defined('WPINC')) {
            $map[includes_url()] = ABSPATH . WPINC;
        }
        if (function_exists('site_url') && defined('ABSPATH')) {
            $map[site_url()] = ABSPATH;
        }

        uksort($map, function ($a, $b) {
            return strlen($b) <=> strlen($a);
        });

        return $map;
    }

    /**
     * @return array{scheme: string, origin: string, path: string}|null
     */
    private static function parseBase(string $baseUrl): ?array
    {
        $parts = @parse_url($baseUrl);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        $origin = $parts['scheme'] . '://' . $parts['host'];
        if (!empty($parts['port'])) {
            $origin .= ':' . $parts['port'];
        }

        return [
            'scheme' => $parts['scheme'],
            'origin' => $origin,
            'path'   => $parts['path'] ?? '/',
        ];
    }

    private static function dirOf(string $path): string
    {
        $slash = strrpos($path, '/');
        return $slash === false ? '' : substr($path, 0, $slash);
    }

    /**
     * Collapse . and .. segments. Keeps any query string on the tail intact.
     */
    private static function normalizePath(string $path): string
    {
        $suffix = '';
        $cut = strcspn($path, '?#');
        if ($cut < strlen($path)) {
            $suffix = substr($path, $cut);
            $path   = substr($path, 0, $cut);
        }

        $out = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($out);
                continue;
            }
            $out[] = $segment;
        }

        return '/' . implode('/', $out) . $suffix;
    }

    private static function stripQuery(string $url): string
    {
        $cut = strcspn($url, '?#');
        return substr($url, 0, $cut);
    }
}
