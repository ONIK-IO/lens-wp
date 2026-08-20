<?php

namespace OnikImages\Admin;

use OnikImages\Activation\Installer;
use OnikImages\Plugin;

class SettingsPage
{
    /**
     * Tab slugs that can appear in ?tab=…. Anything else falls back to
     * 'general'. This is a security boundary: $current_tab is echoed into
     * CSS selectors and a JS string literal in the template, and
     * sanitize_text_field does NOT strip quotes — a crafted value like
     * "';alert(1);//" would XSS without this whitelist.
     */
    private const ALLOWED_TABS = [
        'general',
        'image_settings',
        'youtube_facade',
        'preloads',
        'css_backgrounds',
        'regex_replace',
    ];

    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
            return;
        }

        if (isset($_POST['onik_images_reset'])) {
            check_admin_referer('onik_images_settings-options');
            Installer::install(true);
            wp_redirect(add_query_arg(
                [
                    'page'              => 'onik_images_settings',
                    'settings-updated'  => 'true',
                    'reset'             => 'true',
                ],
                admin_url('options-general.php?page=onik_images_settings')
            ));
            exit;
        }

        $advanced    = AdvancedMode::isEnabled();
        $current_tab = self::resolveTab($_GET['tab'] ?? null);

        require Plugin::pluginDir() . '/views/admin/settings-page.php';
    }

    /**
     * Resolve the requested tab slug to a known value, defaulting to
     * 'general' for anything we don't recognize.
     */
    public static function resolveTab($input): string
    {
        if (!is_string($input)) {
            return 'general';
        }
        $candidate = sanitize_text_field($input);
        return in_array($candidate, self::ALLOWED_TABS, true) ? $candidate : 'general';
    }
}
