<?php

namespace OnikImages\Cron;

use OnikImages\LensActivation;
use OnikImages\LensConnection;

/**
 * Background re-verification via WP-Cron.
 *
 * Re-checking activation/connection only on admin page loads would let a
 * perfectly valid site fail closed just because nobody logged into wp-admin
 * for 30 days (see the last_success grace window in LensActivation /
 * LensConnection). WP-Cron fixes that: it is triggered by ordinary front-end
 * traffic via a non-blocking loopback request, so a live site re-verifies on
 * its own — no admin login, and no delay added to the visitor's page.
 *
 * A genuinely unreachable server still fails every day, so last_success ages
 * out and the site clamps at 30 days as intended.
 */
class Verifier
{
    public const HOOK = 'onik_lens_verify';

    /**
     * Ensure the daily event is scheduled. Idempotent — safe to call on every
     * request (it only schedules when nothing is queued), which is how sites
     * upgrading from a pre-cron version pick the event up without reactivating.
     */
    public static function schedule(): void
    {
        if (!wp_next_scheduled(self::HOOK)) {
            wp_schedule_event(time(), 'daily', self::HOOK);
        }
    }

    public static function unschedule(): void
    {
        wp_clear_scheduled_hook(self::HOOK);
    }

    /**
     * The cron callback. Runs with no logged-in user, so it deliberately skips
     * the is_admin()/current_user_can() gates that Activation\Gate and
     * Connect\Gate apply — cron is a trusted context. Connection is only
     * re-checked once a token has been stored (consent), mirroring the gates.
     */
    public static function run(): void
    {
        $activation = new LensActivation();
        if ($activation->isCheckDue()) {
            $activation->activate();
        }

        $connection = new LensConnection();
        if ($connection->hasToken() && $connection->isCheckDue()) {
            $connection->connect();
        }
    }
}
