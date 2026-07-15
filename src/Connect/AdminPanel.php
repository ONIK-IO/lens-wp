<?php

namespace OnikImages\Connect;

use OnikImages\Admin\AdvancedMode;
use OnikImages\LensConnection;

/**
 * Renders the "Connect to ONIK" panel on the settings page.
 *
 * This lives OUTSIDE the main options.php settings <form>: the token is saved
 * by a dedicated admin-post form (Gate::handleFormSubmission), not the Settings
 * API, and nesting forms is invalid HTML. It is echoed from the settings-page
 * view near the activation panel.
 */
class AdminPanel
{
    public static function render(): void
    {
        $connection = new LensConnection();
        $token      = (string) get_option('onik_lens_jwt', '');
        $status     = $connection->getStatus();
        $connected  = $connection->isConnected();
        $tenant     = (string) get_option('onik_images_tenant', '');
        $site       = (string) get_option('onik_images_site', '');
        $action_url = esc_url(admin_url('options-general.php?page=onik_images_settings&tab=general'));

        self::renderAttemptNotice($connection);

        // The `inline` class is load-bearing: without it WordPress admin JS
        // (common.js) hoists any `.notice` element up to just below the page
        // <h1>, which would pull this panel out of the General tab and back
        // above the tab bar. `inline` opts out of that relocation.
        echo '<div class="notice notice-info inline" style="padding: 12px 16px;">';

        if ($connected) {
            self::renderConnected($connection, $token, $tenant, $site, $action_url);
        } else {
            self::renderDisconnected($connection, $token, $status, $action_url);
        }

        echo '</div>';
    }

    /**
     * Connected happy path: collapse to a one-line "Connected to ONIK" with a
     * Reconnect toggle (native <details>, no JS) that reveals the paste form.
     */
    private static function renderConnected(LensConnection $connection, string $token, string $tenant, string $site, string $action_url): void
    {
        echo '<style>'
            . 'details.onik-connected>summary{list-style:none;cursor:pointer;}'
            . 'details.onik-connected>summary::-webkit-details-marker{display:none;}'
            . 'details.onik-connected .onik-reconnect{color:#2271b1;text-decoration:underline;margin-left:12px;font-weight:400;}'
            . '</style>';
        echo '<details class="onik-connected">';
        echo '<summary><span style="color:#46b450;font-weight:600;">&#10003; Connected to ONIK</span>'
            . '<span class="onik-reconnect">Reconnect</span></summary>';
        echo '<div style="margin-top:12px;">';
        if ($tenant !== '' || $site !== '') {
            echo '<p style="color:#555;margin-top:0;">Connected as <code>' . esc_html($tenant) . '</code> / <code>' . esc_html($site) . '</code>.</p>';
        }
        self::renderInstructions($token);
        self::renderTokenForm($token, $action_url);
        self::renderAdvanced($connection, $token, $action_url);
        echo '</div>';
        echo '</details>';
    }

    /**
     * Trial / not-connected / lapsed states: full panel with status line,
     * instructions, and the paste form always visible.
     */
    private static function renderDisconnected(LensConnection $connection, string $token, array $status, string $action_url): void
    {
        echo '<h3 style="margin-top:0;">Connect to ONIK</h3>';

        echo '<p><strong>Status:</strong> ';
        if ($token !== '') {
            $msg = $status['message'] ?: $status['reason'];
            echo '<span style="color:#dc3232;">&#10007; Not connected';
            echo $msg ? ': ' . esc_html($msg) : '';
            echo '</span>';
        } else {
            echo '<span style="color:#2271b1;">&#9679; Running in trial mode</span>';
        }
        echo '</p>';

        self::renderInstructions($token);
        self::renderTokenForm($token, $action_url);
        self::renderAdvanced($connection, $token, $action_url);
    }

    private static function renderInstructions(string $token): void
    {
        if ($token === '') {
            echo '<p><strong>Free trial mode.</strong> The plugin is fully featured &mdash; enough to show off the benefits of ONIK Lens and test it on your site. '
                . 'ONIK Lens uses a CDN and storage to achieve its speed, so a subscription is required for production sites. '
                . 'For plans, limits, and more info see '
                . '<a href="https://onik.io/pricing" target="_blank" rel="noopener">onik.io/pricing</a> and '
                . '<a href="https://onik.io/wp/lens" target="_blank" rel="noopener">onik.io/wp/lens</a>.</p>';
            echo '<p style="margin-bottom:4px;"><strong>To connect this site to your ONIK subscription:</strong></p>';
            echo '<ol style="margin: 0 0 10px 20px;">'
                . '<li>Open your site at <a href="https://app.onik.io" target="_blank" rel="noopener">app.onik.io</a>. You may need to create an account and register your site first.</li>'
                . '<li>In the left-hand menu, open <strong>Lens Image Optimization</strong>.</li>'
                . '<li>Copy the token on that page and paste it into the box below, then click <strong>Connect to ONIK</strong>.</li>'
                . '</ol>';
            echo '<p style="margin-top:0;">This links this Lens to your ONIK site and subscription.</p>';
        } else {
            echo '<p>To reconnect, copy a fresh token from <strong>Lens Image Optimization</strong> at '
                . '<a href="https://app.onik.io" target="_blank" rel="noopener">app.onik.io</a> and paste it below. '
                . 'ONIK returns your tenant and site, which the plugin uses to route images through the CDN.</p>';
        }
    }

    private static function renderTokenForm(string $token, string $action_url): void
    {
        // The token itself is a credential: the textarea always renders empty
        // so a stored token is never echoed back into the page.
        $placeholder  = $token === ''
            ? 'Paste your ONIK site token (JWT) here'
            : 'A token is already saved. Paste a new one to replace it.';
        $button_label = $token === '' ? 'Connect to ONIK' : 'Reconnect';
        $nonce_field  = wp_nonce_field('onik_lens_connect_action', 'onik_lens_connect_nonce', true, false);

        echo '<form method="post" action="' . $action_url . '" style="margin-top: 8px;">';
        echo '<input type="hidden" name="onik_lens_connect_now" value="1" />';
        echo $nonce_field;
        echo '<p><textarea name="onik_lens_jwt" rows="3" class="large-text code" '
            . 'placeholder="' . esc_attr($placeholder) . '" '
            . 'style="font-family:monospace;"></textarea></p>';
        echo '<p style="margin-bottom:0;"><input type="submit" class="button button-primary" value="'
            . esc_attr($button_label) . '" /></p>';
        echo '</form>';
    }

    private static function renderAttemptNotice(LensConnection $connection): void
    {
        if (isset($_GET['connection-empty']) && $_GET['connection-empty'] === '1') {
            echo '<div class="notice notice-warning is-dismissible"><p><strong>No token entered.</strong> '
                . 'Paste your ONIK site token, then click Connect to ONIK. Your existing connection was left unchanged.</p></div>';
            return;
        }

        if (!isset($_GET['connection-attempted']) || $_GET['connection-attempted'] !== '1') {
            return;
        }

        if ($connection->isConnected()) {
            echo '<div class="notice notice-success is-dismissible"><p><strong>Connected!</strong> '
                . 'Your ONIK Lens tenant and site are configured.</p></div>';
            return;
        }

        $status    = $connection->getStatus();
        $msg       = esc_html($status['message'] ?: $status['reason'] ?: 'Connection failed. Check your site token.');
        $clear_url = esc_url(remove_query_arg('connection-attempted'));
        echo '<div class="notice notice-error is-dismissible"><p><strong>Connection failed:</strong> '
            . $msg . ' <a href="' . $clear_url . '">clear</a></p></div>';
    }

    /**
     * Advanced Mode extras: raw connection option values and a Disconnect
     * (test) button that clears the stored token and cached state.
     */
    private static function renderAdvanced(LensConnection $connection, string $token, string $action_url): void
    {
        if (!AdvancedMode::isEnabled()) {
            return;
        }

        if (isset($_GET['disconnection-done']) && $_GET['disconnection-done'] === '1') {
            echo '<div class="notice notice-info inline" style="margin: 10px 0;"><p>Connection state cleared.</p></div>';
        }

        $debug_rows = [
            'onik_lens_jwt'                    => $token === '' ? '(empty)' : '(set, ' . strlen($token) . ' chars)',
            'onik_lens_connected'             => get_option('onik_lens_connected', ''),
            'onik_lens_connection_reason'     => get_option('onik_lens_connection_reason', ''),
            'onik_lens_connection_message'    => get_option('onik_lens_connection_message', ''),
            'onik_lens_connection_next_check' => get_option('onik_lens_connection_next_check', ''),
        ];

        echo '<table class="form-table" role="presentation"><tbody>';
        foreach ($debug_rows as $key => $value) {
            echo '<tr>';
            echo '<th scope="row">' . esc_html($key) . '</th>';
            echo '<td>' . esc_html($value !== '' ? $value : '(empty)');

            if ($key === 'onik_lens_connected') {
                $nonce_field = wp_nonce_field('onik_lens_disconnect_action', 'onik_lens_disconnect_nonce', true, false);
                echo ' &nbsp; <form method="post" action="' . $action_url . '" style="display:inline;">'
                    . '<input type="hidden" name="onik_lens_disconnect_now" value="1" />'
                    . $nonce_field
                    . '<button type="submit" class="button-link" style="color:#a00;">Disconnect (test)</button>'
                    . '</form>';
            }

            echo '</td></tr>';
        }
        echo '</tbody></table>';
    }
}
