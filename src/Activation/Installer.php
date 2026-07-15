<?php

namespace OnikImages\Activation;

use OnikImages\Cron\Verifier;
use OnikImages\LensActivation;

class Installer
{
    /**
     * WordPress plugin-activation callback (register_activation_hook).
     *
     * Seeds defaults, then runs the subscription check immediately. Activation
     * sends no personal data (see LensActivation::activate), so it can run
     * automatically here rather than behind a consent dialog — the user
     * activating the plugin is the trigger.
     */
    public static function onActivate(): void
    {
        self::install();
        Verifier::schedule();
        (new LensActivation())->activate();
    }

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
        'onik_lens_activation_last_success'  => '',
        'onik_lens_jwt'                      => '',
        'onik_lens_connected'                => '0',
        'onik_lens_connection_reason'        => '',
        'onik_lens_connection_message'       => '',
        'onik_lens_connection_next_check'    => '',
        'onik_lens_connection_last_success'  => '',
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
    }
}
