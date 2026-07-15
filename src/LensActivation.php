<?php

namespace OnikImages;

use OnikImages\Support\ApiEndpoints;

class LensActivation
{
    /**
     * Grace window. A one-time activation does not grant access forever: if the
     * last SUCCESSFUL check is more than this old, the site fails closed until
     * it re-verifies. Refreshed by any successful check — admin-load or WP-Cron
     * (Cron\Verifier), so a valid site stays fresh without anyone logging in.
     * Guards against a site that activated once and then permanently lost the
     * server (outage, firewall, DNS) running indefinitely.
     */
    private const STALE_AFTER_SECONDS = 30 * 24 * 60 * 60;

    public function activate(): bool
    {
        // Clear stale options so they are refreshed by this activation check.
        update_option('onik_lens_activation_reason', '');
        update_option('onik_lens_activation_message', '');
        update_option('onik_lens_activation_next_check', '');

        // Deliberately excludes any private data (e.g. the admin email).
        // Activation is an anonymous subscription check; identifying the site
        // is the job of Connect (OnikImages\LensConnection).
        $payload = [
            'wpSite'        => get_bloginfo('name'),
            'wpUrl'         => get_site_url(),
            'tenant'        => get_option('onik_images_tenant', ''),
            'site'          => get_option('onik_images_site', ''),
            'pluginVersion' => defined('ONIK_IMAGES_VERSION') ? ONIK_IMAGES_VERSION : '',
        ];

        $response = wp_remote_post(ApiEndpoints::activate(), [
            'body'    => wp_json_encode($payload),
            'headers' => ['Content-Type' => 'application/json'],
            'timeout' => 15,
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            $this->storeHttpError($response);
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $this->storeResponse($body);

        if (isset($body['activated']) && $body['activated'] === false) {
            return false;
        }

        return (bool) ($body['activated'] ?? false);
    }

    public function isCheckDue(): bool
    {
        $next_check = get_option('onik_lens_activation_next_check', '');

        if ($next_check === '' || $next_check === false) {
            return true;
        }

        $dt = \DateTime::createFromFormat(\DateTime::ATOM, $next_check);
        if ($dt === false) {
            return true;
        }

        return new \DateTime() > $dt;
    }

    public function isActivated(): bool
    {
        if (get_option('onik_lens_activated', '0') !== '1') {
            return false;
        }

        return !$this->isStale();
    }

    /**
     * True when the last successful check is older than the grace window.
     * A missing/unparseable timestamp (activated under an older version, or no
     * success recorded yet) is treated as NOT stale — the next successful check
     * (admin or cron) stamps it, so existing installs are never clamped on
     * upgrade.
     */
    private function isStale(): bool
    {
        $last = get_option('onik_lens_activation_last_success', '');
        if ($last === '' || $last === false) {
            return false;
        }
        $dt = \DateTime::createFromFormat(\DateTime::ATOM, $last);
        if ($dt === false) {
            return false;
        }

        return (new \DateTime())->getTimestamp() - $dt->getTimestamp() > self::STALE_AFTER_SECONDS;
    }

    public function getStatus(): array
    {
        return [
            'reason'  => get_option('onik_lens_activation_reason', ''),
            'message' => get_option('onik_lens_activation_message', ''),
        ];
    }

    public function scheduleImmediateCheck(): void
    {
        update_option('onik_lens_activation_next_check', '');
    }

    private function storeResponse(array $body): void
    {
        $activated = isset($body['activated']) && $body['activated'] ? '1' : '0';
        update_option('onik_lens_activated', $activated);
        update_option('onik_lens_activation_reason', $body['reason'] ?? '');
        update_option('onik_lens_activation_message', $body['message'] ?? '');

        // Stamp the last successful check — the staleness anchor. Only reached
        // on a real 200 verdict, never on a transient error, so it truly marks
        // "last time we heard back from the server".
        update_option('onik_lens_activation_last_success', (new \DateTime())->format(\DateTime::ATOM));
        update_option('onik_lens_activation_next_check', (new \DateTime('+24 hours'))->format(\DateTime::ATOM));
    }

    private function storeHttpError($response): void
    {
        // Deliberately does NOT touch onik_lens_activated. A network/HTTP
        // failure is not a server verdict — only a 200 response (storeResponse)
        // may flip activated. This keeps a transient blip during the periodic
        // check from deactivating a working (trial or live) site.
        update_option('onik_lens_activation_reason', 'network_error');

        $message = null;
        if (!is_wp_error($response)) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (isset($body['message']) && $body['message'] !== '') {
                $message = $body['message'];
            }
        }

        if ($message === null) {
            $message = 'Could not reach the activation server. Please try again later.';
            if (is_wp_error($response)) {
                $message .= ' (' . $response->get_error_message() . ')';
            } else {
                $code = wp_remote_retrieve_response_code($response);
                if ($code !== null && $code !== false) {
                    $message .= ' (HTTP ' . $code . ')';
                }
            }
        }

        update_option('onik_lens_activation_message', $message);
        update_option('onik_lens_activation_next_check', (new \DateTime('+1 hour'))->format(\DateTime::ATOM));
    }
}
