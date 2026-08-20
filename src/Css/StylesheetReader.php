<?php

namespace OnikImages\Css;

/**
 * Reads a stylesheet off local disk and hands its background-image records
 * back, cached.
 *
 * Local files only, on purpose. Fetching an off-host stylesheet would mean an
 * HTTP round trip during a page render, which is bad for latency and is the
 * kind of thing WP.org reviewers push back on. Off-host sheets are reported as
 * skipped rather than fetched.
 */
class StylesheetReader
{
    /** Transient key prefix. */
    private const CACHE_PREFIX = 'onik_lens_css_';

    /** Cache lifetime. The fingerprint invalidates sooner when the file changes. */
    private const CACHE_TTL = 43200;

    /** Refuse anything larger. A stylesheet past this is not hand-written CSS. */
    private const MAX_BYTES = 2097152;

    public const ERROR_NOT_LOCAL = 'not-local';
    public const ERROR_TOO_LARGE = 'too-large';
    public const ERROR_UNREADABLE = 'unreadable';

    /**
     * @return array{
     *   ok: bool,
     *   error: ?string,
     *   path: ?string,
     *   bytes: int,
     *   cached: bool,
     *   records: array<int, array<string, mixed>>
     * }
     */
    public static function read(string $url, bool $useCache = true): array
    {
        $url = trim($url);

        $path = UrlResolver::toLocalPath($url);
        if ($path === null) {
            return self::failure(self::ERROR_NOT_LOCAL);
        }

        $bytes = @filesize($path);
        if ($bytes === false) {
            return self::failure(self::ERROR_UNREADABLE, $path);
        }
        if ($bytes > self::MAX_BYTES) {
            return self::failure(self::ERROR_TOO_LARGE, $path, (int) $bytes);
        }

        $key = self::cacheKey($url, $path, (int) $bytes);

        if ($useCache) {
            $cached = self::getTransient($key);
            if (is_array($cached)) {
                return [
                    'ok'      => true,
                    'error'   => null,
                    'path'    => $path,
                    'bytes'   => (int) $bytes,
                    'cached'  => true,
                    'records' => $cached,
                ];
            }
        }

        $css = @file_get_contents($path);
        if ($css === false) {
            return self::failure(self::ERROR_UNREADABLE, $path, (int) $bytes);
        }

        $records = CssScanner::scan($css, $url);
        self::setTransient($key, $records);

        return [
            'ok'      => true,
            'error'   => null,
            'path'    => $path,
            'bytes'   => (int) $bytes,
            'cached'  => false,
            'records' => $records,
        ];
    }

    /**
     * Pixel dimensions and byte size for an image URL, when it resolves to a
     * local file. The discovery table uses this to show which backgrounds are
     * actually worth optimizing.
     *
     * @return array{width: ?int, height: ?int, bytes: ?int}
     */
    public static function describeImage(string $url): array
    {
        $unknown = ['width' => null, 'height' => null, 'bytes' => null];

        $path = UrlResolver::toLocalPath($url);
        if ($path === null) {
            return $unknown;
        }

        $bytes = @filesize($path);
        $size  = @getimagesize($path);
        if ($size === false) {
            return ['width' => null, 'height' => null, 'bytes' => $bytes === false ? null : (int) $bytes];
        }

        return [
            'width'  => (int) $size[0],
            'height' => (int) $size[1],
            'bytes'  => $bytes === false ? null : (int) $bytes,
        ];
    }

    /**
     * Keyed on the file's identity plus its fingerprint, so an edited
     * stylesheet invalidates itself without waiting for the TTL. Astra writes
     * one dynamic file per post, so keys scale with pages viewed; the TTL is
     * what bounds that.
     */
    private static function cacheKey(string $url, string $path, int $bytes): string
    {
        $mtime = @filemtime($path);
        $fingerprint = ($mtime === false ? '0' : (string) $mtime) . ':' . $bytes;

        return self::CACHE_PREFIX . md5($url . '|' . $fingerprint);
    }

    /**
     * @return array{ok: bool, error: ?string, path: ?string, bytes: int, cached: bool, records: array}
     */
    private static function failure(string $error, ?string $path = null, int $bytes = 0): array
    {
        return [
            'ok'      => false,
            'error'   => $error,
            'path'    => $path,
            'bytes'   => $bytes,
            'cached'  => false,
            'records' => [],
        ];
    }

    private static function getTransient(string $key)
    {
        return function_exists('get_transient') ? get_transient($key) : false;
    }

    private static function setTransient(string $key, array $value): void
    {
        if (function_exists('set_transient')) {
            set_transient($key, $value, self::CACHE_TTL);
        }
    }
}
