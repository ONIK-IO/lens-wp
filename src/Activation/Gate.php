<?php

namespace OnikImages\Activation;

use OnikImages\LensActivation;

class Gate
{
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
     * Re-run the activation check when the cached result has expired. Gated on
     * admin + manage_options, so it only fires on admin page loads (every day
     * or two, per the +24h next_check window).
     *
     * Activation sends no personal data, so — unlike Connect — there is no
     * consent gate: an empty next_check (fresh install, or a site upgrading
     * from before the automatic-activation change) is treated as "due" and
     * activates on the next admin load.
     */
    public static function checkIfDue(): void
    {
        if (!is_admin() || !current_user_can('manage_options')) {
            return;
        }

        $activation = new LensActivation();
        if ($activation->isCheckDue()) {
            $activation->activate();
        }
    }
}
