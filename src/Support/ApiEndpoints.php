<?php

namespace OnikImages\Support;

/**
 * Resolves the ONIK API endpoint URLs used by Activate and Connect.
 *
 * The base defaults to production (https://app.onik.io) but can be overridden
 * for local development or staging without editing source. In precedence order
 * (lowest to highest):
 *
 *   1. ONIK_LENS_API_BASE environment variable — handy in Docker: set it in
 *      the testbed's docker-compose.yml and the plugin picks it up via getenv().
 *   2. ONIK_LENS_API_BASE constant — e.g. define() in wp-config.php.
 *   3. onik_lens_api_base filter — programmatic override, always wins.
 *
 * Example (either mechanism):
 *
 *     define('ONIK_LENS_API_BASE', 'http://localhost:3000');   // wp-config.php
 *     ONIK_LENS_API_BASE=http://host.docker.internal:3000      // docker-compose
 *
 * Paths are appended to the resolved base, so the override is just the origin.
 */
class ApiEndpoints
{
    private const DEFAULT_BASE = 'https://app.onik.io';

    public static function base(): string
    {
        $base = self::DEFAULT_BASE;

        $env = getenv('ONIK_LENS_API_BASE');
        if (is_string($env) && $env !== '') {
            $base = $env;
        }

        if (defined('ONIK_LENS_API_BASE') && ONIK_LENS_API_BASE) {
            $base = ONIK_LENS_API_BASE;
        }

        if (function_exists('apply_filters')) {
            $base = apply_filters('onik_lens_api_base', $base);
        }

        return rtrim((string) $base, '/');
    }

    public static function activate(): string
    {
        return self::base() . '/api/lens/activate';
    }

    public static function connect(): string
    {
        return self::base() . '/api/connect/site-token';
    }
}
