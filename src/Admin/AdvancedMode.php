<?php

namespace OnikImages\Admin;

/**
 * Advanced mode is a hidden admin toggle stored in a cookie.
 *
 * Visiting the settings page with ?admin=1 turns it on for 30 days; ?admin=0
 * turns it off. When on, extra fields render on the settings page (Image
 * Converter URL, Tenant/Site, domain allowlists, Regex Replace, Script Block).
 */
class AdvancedMode
{
    private const COOKIE = 'onik_images_advanced_mode';

    public static function checkToggle(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (!isset($_GET['admin'])) {
            return;
        }

        $mode = sanitize_text_field($_GET['admin']);
        if ($mode === '1') {
            setcookie(self::COOKIE, '1', time() + 30 * DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl());
            $_COOKIE[self::COOKIE] = '1';
        } elseif ($mode === '0') {
            setcookie(self::COOKIE, '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, is_ssl());
            unset($_COOKIE[self::COOKIE]);
        }
    }

    public static function isEnabled(): bool
    {
        return isset($_COOKIE[self::COOKIE]) && $_COOKIE[self::COOKIE] === '1';
    }
}
