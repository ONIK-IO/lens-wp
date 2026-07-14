<?php

namespace OnikImages\Admin\Renderers;

use OnikImages\Admin\AdvancedMode;
use OnikImages\Admin\FieldHelpers;
use OnikImages\LensActivation;

/**
 * Small field renderers for the General Settings section.
 *
 * Each public static method matches one add_settings_field() callback wired in
 * Admin\SettingsRegistry. The bigger per-setting renderers (image, youtube,
 * preloads, etc.) live in their own classes alongside this one.
 */
class GeneralRenderers
{
    public static function accountSection(): void
    {
        echo '<p>Configure your ONIK Lens account credentials.</p>';

        $activation = new LensActivation();
        $next_check = get_option('onik_lens_activation_next_check', '');
        $status = $activation->getStatus();

        echo '<table class="form-table" role="presentation"><tbody><tr>';
        echo '<th scope="row">Activation Status</th>';
        echo '<td>';

        if ($next_check === '' || $next_check === false) {
            echo '<span style="color:#888;">&#8212; Not yet checked</span>';
        } elseif ($activation->isActivated()) {
            echo '<span style="color:#46b450;">&#10003; Activated</span>';
        } else {
            $msg = esc_html($status['message'] ?: $status['reason']);
            echo '<span style="color:#dc3232;">&#10007; Not activated' . ($msg ? ': ' . $msg : '') . '</span>';
        }

        echo '</td></tr></tbody></table>';
    }

    public static function imageConverterUrl(): void
    {
        FieldHelpers::text('onik_images_image_converter_url');
        echo '<p>Enter the base URL for the ONIK image converter service. This field is required when ONIK Lens is enabled and must end with a trailing slash (/).</p>';
    }

    public static function enabled(): void
    {
        FieldHelpers::checkbox('onik_images_enabled');
        echo '<p style="">When unchecked, the plugin will have no effect on the front end</p>';

        if (!AdvancedMode::isEnabled()) {
            return;
        }

        if (isset($_GET['deactivation-done']) && $_GET['deactivation-done'] === '1') {
            echo '<div class="notice notice-info inline" style="margin: 10px 0;"><p>Activation state cleared. Reload the page to see the fresh-install Activate panel.</p></div>';
        }

        $debug_rows = [
            'onik_lens_activated'             => get_option('onik_lens_activated', ''),
            'onik_lens_activation_reason'     => get_option('onik_lens_activation_reason', ''),
            'onik_lens_activation_message'    => get_option('onik_lens_activation_message', ''),
            'onik_lens_activation_next_check' => get_option('onik_lens_activation_next_check', ''),
        ];

        echo '<table class="form-table" role="presentation"><tbody>';
        foreach ($debug_rows as $key => $value) {
            echo '<tr>';
            echo '<th scope="row">' . esc_html($key) . '</th>';
            echo '<td>' . esc_html($value !== '' ? $value : '(empty)');

            // Inline Deactivate (test) form, next to the activated row only.
            // Clears all four cached activation fields so the next page load
            // looks like a fresh install.
            if ($key === 'onik_lens_activated') {
                $action_url  = esc_url(admin_url('options-general.php?page=onik_images_settings&tab=general'));
                $nonce_field = wp_nonce_field('onik_lens_deactivate_action', 'onik_lens_deactivate_nonce', true, false);
                echo ' &nbsp; <form method="post" action="' . $action_url . '" style="display:inline;">'
                    . '<input type="hidden" name="onik_lens_deactivate_now" value="1" />'
                    . $nonce_field
                    . '<button type="submit" class="button-link" style="color:#a00;">Deactivate (test)</button>'
                    . '</form>';
            }

            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    public static function tenant(): void
    {
        FieldHelpers::text('onik_images_tenant');
    }

    public static function site(): void
    {
        FieldHelpers::text('onik_images_site');
    }

    public static function allowDomains(): void
    {
        FieldHelpers::text('onik_images_allow_domains');
        echo '<p>Enter the domains hosting images you want ONIK to manage. Separate multiple domains with a comma. <br />If you leave this blank, ONIK will include images from all domains.</p>';
    }

    public static function forbiddenDomains(): void
    {
        FieldHelpers::text('onik_images_forbidden_domains');
        echo '<p>Enter domains that should be excluded from ONIK processing. Separate multiple domains with a comma. <br />Default: localhost,127.0.0.1 (when empty or not set).</p>';
    }

    public static function debug(): void
    {
        FieldHelpers::checkbox('onik_images_debug');
    }
}
