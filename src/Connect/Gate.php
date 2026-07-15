<?php

namespace OnikImages\Connect;

use OnikImages\LensConnection;

class Gate
{
    /**
     * Handle the "Connect to ONIK" form submission in the admin: save the
     * pasted site token, then exchange it for tenant/site + a fresh token.
     */
    public static function handleFormSubmission(): void
    {
        if (!isset($_POST['onik_lens_connect_now'])) {
            return;
        }
        if (!current_user_can('manage_options')) {
            return;
        }
        check_admin_referer('onik_lens_connect_action', 'onik_lens_connect_nonce');

        $raw       = isset($_POST['onik_lens_jwt']) ? wp_unslash($_POST['onik_lens_jwt']) : '';
        $candidate = self::sanitizeToken($raw);

        // Empty submission (e.g. clicking Reconnect without pasting): do not
        // touch the stored token or connection state, so a stray click can't
        // disconnect a working site.
        if ($candidate === '') {
            wp_redirect(add_query_arg([
                'page'             => 'onik_images_settings',
                'tab'              => 'general',
                'connection-empty' => '1',
            ], admin_url('options-general.php')));
            exit;
        }

        // Pass the pasted token as a candidate — it is only persisted if the
        // server accepts it, so a bad token never overwrites a good one.
        (new LensConnection())->connect($candidate);

        wp_redirect(add_query_arg([
            'page'                 => 'onik_images_settings',
            'tab'                  => 'general',
            'connection-attempted' => '1',
        ], admin_url('options-general.php')));
        exit;
    }

    /**
     * Handle the Advanced Mode "Disconnect (test)" button. Clears the stored
     * token and cached connection state so the next admin page load behaves
     * like a site that has never connected. Strictly a testing affordance —
     * only exposed when Advanced Mode is on.
     */
    public static function handleDisconnect(): void
    {
        if (!isset($_POST['onik_lens_disconnect_now'])) {
            return;
        }
        if (!current_user_can('manage_options')) {
            return;
        }
        check_admin_referer('onik_lens_disconnect_action', 'onik_lens_disconnect_nonce');

        update_option('onik_lens_jwt', '');
        update_option('onik_lens_connected', '0');
        update_option('onik_lens_connection_reason', '');
        update_option('onik_lens_connection_message', '');
        update_option('onik_lens_connection_next_check', '');

        wp_redirect(add_query_arg([
            'page'               => 'onik_images_settings',
            'tab'                => 'general',
            'disconnection-done' => '1',
        ], admin_url('options-general.php')));
        exit;
    }

    /**
     * Refresh the connection if the cached check has expired. Gated on
     * admin + manage_options.
     *
     * Will NOT phone home before the user has connected at least once. The
     * signal is a stored token: onik_lens_jwt is empty until the user pastes
     * one and clicks Connect. This keeps the connect endpoint quiet on sites
     * that never opted in (WP.org Plugin Guideline 7).
     */
    public static function checkIfDue(): void
    {
        if (!is_admin() || !current_user_can('manage_options')) {
            return;
        }

        $connection = new LensConnection();
        if (!$connection->hasToken()) {
            return;
        }

        if ($connection->isCheckDue()) {
            $connection->connect();
        }
    }

    /**
     * Reduce a pasted value to the JWT character set. A JWT is three
     * base64url segments joined by dots, so only [A-Za-z0-9._-] is valid;
     * stripping everything else drops stray whitespace, quotes, or a copied
     * "Bearer " prefix without corrupting the token.
     */
    private static function sanitizeToken(string $raw): string
    {
        return preg_replace('/[^A-Za-z0-9._\-]/', '', trim($raw));
    }
}
