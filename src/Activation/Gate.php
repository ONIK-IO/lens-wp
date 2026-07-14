<?php

namespace OnikImages\Activation;

use OnikImages\LensActivation;

class Gate
{
    /**
     * Handle the manual "Check Activation Now" form submission in the admin.
     */
    public static function handleFormSubmission(): void
    {
        if (!isset($_POST['onik_lens_activate_now'])) {
            return;
        }
        if (!current_user_can('manage_options')) {
            return;
        }
        check_admin_referer('onik_lens_activate_action', 'onik_lens_activate_nonce');
        (new LensActivation())->activate();
        wp_redirect(add_query_arg([
            'page'                  => 'onik_images_settings',
            'tab'                   => 'general',
            'activation-attempted'  => '1',
        ], admin_url('options-general.php')));
        exit;
    }

    /**
     * Handle the Advanced Mode "Deactivate (test)" button. Clears the cached
     * activation state so the next admin page load behaves like a fresh
     * install. Strictly a testing affordance — only exposed when Advanced
     * Mode is on (the Deactivate button only renders there).
     */
    public static function handleDeactivate(): void
    {
        if (!isset($_POST['onik_lens_deactivate_now'])) {
            return;
        }
        if (!current_user_can('manage_options')) {
            return;
        }
        check_admin_referer('onik_lens_deactivate_action', 'onik_lens_deactivate_nonce');

        update_option('onik_lens_activated', '0');
        update_option('onik_lens_activation_reason', '');
        update_option('onik_lens_activation_message', '');
        update_option('onik_lens_activation_next_check', '');

        wp_redirect(add_query_arg([
            'page'                 => 'onik_images_settings',
            'tab'                  => 'general',
            'deactivation-done'    => '1',
        ], admin_url('options-general.php')));
        exit;
    }

    /**
     * Re-activate if the cached check has expired. Gated on admin + manage_options.
     *
     * Will NOT phone home before the user has explicitly initiated activation
     * at least once (via the Activate button → handleFormSubmission). The
     * signal for that is onik_lens_activation_next_check being set: it's
     * empty by default and only gets stamped once LensActivation::activate()
     * runs. This satisfies WP.org Plugin Guideline 7 (no automated data
     * collection without user consent).
     */
    public static function checkIfDue(): void
    {
        if (!is_admin() || !current_user_can('manage_options')) {
            return;
        }

        $next_check = get_option('onik_lens_activation_next_check', '');
        if ($next_check === '' || $next_check === false) {
            return;
        }

        $activation = new LensActivation();
        if ($activation->isCheckDue()) {
            $activation->activate();
        }
    }
}
