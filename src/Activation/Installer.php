<?php

namespace OnikImages\Activation;

class Installer
{
    private const DEFAULTS = [
        'onik_images_forbidden_domains'      => 'localhost,127.0.0.1',
        'onik_images_allow_domains'          => '',
        'onik_images_tenant'                 => 'trial',
        'onik_images_image_converter_url'    => 'https://images.onik.io/',
        'onik_images_enabled'                => '1',
        'onik_images_debug'                  => '0',
        'onik_images_youtube_enabled'        => '0',
        'onik_images_image_settings'         => '{"img":{"quality":60, "srcSwap":"srcAndSrcSet", "format":"auto"}}',
        'onik_images_youtube_settings'       => '{"iframe[src*=\'youtube\']": {},".elementor-widget-video":{}}',
        'onik_lens_activated'                => '0',
        'onik_lens_activation_reason'        => '',
        'onik_lens_activation_message'       => '',
        'onik_lens_activation_next_check'    => '',
    ];

    public static function install(bool $reset = false): void
    {
        foreach (self::DEFAULTS as $option => $value) {
            if (get_option($option) === false || $reset) {
                update_option($option, $value);
            }
        }

        // Site default depends on get_site_url(), so handle separately.
        if (get_option('onik_images_site') === false || $reset) {
            update_option('onik_images_site', preg_replace('#^https?://#', '', get_site_url()));
        }

        // Intentionally do NOT call Gate::checkIfDue() here. WordPress.org
        // Plugin Guideline 7 forbids "automated collection of user data
        // without explicit confirmation". The activation API POST (which
        // sends the admin email to onik.io) must wait until the user
        // clicks Activate on the settings page — see Gate::handleFormSubmission.
    }
}
