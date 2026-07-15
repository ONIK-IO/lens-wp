<?php

namespace OnikImages;

use OnikImages\Support\ApiEndpoints;

/**
 * Client for connecting this WordPress site to an ONIK Lens account.
 *
 * "Connect" is deliberately distinct from "Activate" (OnikImages\LensActivation):
 *
 *   - Activate is an anonymous subscription check. It sends no private data and
 *     gates whether the plugin rewrites output at all.
 *   - Connect is an authenticated identity link. The user pastes a site token
 *     (JWT) copied from the Lens Image Optimization page at app.onik.io; we
 *     POST it to the connect
 *     endpoint, which returns the tenant/site identity plus a fresh, rotated
 *     token. From the response we populate onik_images_tenant and
 *     onik_images_site — the two path segments used to build CDN URLs.
 *
 * The plugin never decodes the JWT itself: it is opaque here and validated
 * server-side. Everything we need (tenant, site, a rotated token, the connected
 * verdict) comes back as plain JSON in the response body.
 */
class LensConnection
{
    /**
     * Grace window, mirroring LensActivation. A connection whose last SUCCESSFUL
     * check is older than this fails closed until it re-verifies, so a stale
     * "connected" verdict cannot keep a subscription alive forever after the
     * server becomes unreachable. Refreshed by any successful check, admin-load
     * or WP-Cron (Cron\Verifier).
     */
    private const STALE_AFTER_SECONDS = 30 * 24 * 60 * 60;

    /**
     * Exchange the stored site token for a fresh one and sync tenant/site.
     *
     * Returns true only when the endpoint confirms the site is connected.
     */
    public function connect(?string $token = null): bool
    {
        // Clear stale status so it reflects only this attempt.
        update_option('onik_lens_connection_reason', '');
        update_option('onik_lens_connection_message', '');
        update_option('onik_lens_connection_next_check', '');

        // A caller-supplied token (from the Connect form) is a *candidate*: it
        // is only persisted if the server accepts it (storeResponse), so a bad
        // paste never clobbers a working connection. With no argument we
        // re-verify the token already stored — the periodic refresh path.
        $jwt = $token !== null ? trim($token) : trim((string) get_option('onik_lens_jwt', ''));
        if ($jwt === '') {
            update_option('onik_lens_connected', '0');
            update_option('onik_lens_connection_reason', 'missing_token');
            update_option('onik_lens_connection_message', 'Paste your ONIK site token, then click Connect to ONIK.');
            // No token means nothing to phone home about — leave next_check
            // empty so Connect\Gate::checkIfDue() stays quiet until the user
            // actually connects.
            return false;
        }

        $response = wp_remote_post(ApiEndpoints::connect(), [
            'body'    => wp_json_encode(['token' => $jwt]),
            'headers' => ['Content-Type' => 'application/json'],
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            $this->storeHttpError($response);
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($body)) {
            $body = [];
        }

        // An explicit connected verdict is authoritative regardless of HTTP
        // status: a paused or lapsed site can come back non-200 with
        // connected.isConnected=false, and that must flip us to not-connected.
        // Only a response with NO verdict (network failure, opaque 5xx,
        // malformed-token 401) is treated as transient and leaves state alone.
        if (isset($body['connected']['isConnected'])) {
            return $this->storeResponse($body, $jwt);
        }

        $this->storeHttpError($response);
        return false;
    }

    public function isCheckDue(): bool
    {
        $next_check = get_option('onik_lens_connection_next_check', '');

        if ($next_check === '' || $next_check === false) {
            return true;
        }

        $dt = \DateTime::createFromFormat(\DateTime::ATOM, $next_check);
        if ($dt === false) {
            return true;
        }

        return new \DateTime() > $dt;
    }

    public function isConnected(): bool
    {
        if (get_option('onik_lens_connected', '0') !== '1') {
            return false;
        }

        return !$this->isStale();
    }

    /**
     * True when the last successful check is older than the grace window. A
     * missing/unparseable timestamp is treated as NOT stale so existing
     * connected installs are not clamped before the next successful check.
     */
    private function isStale(): bool
    {
        $last = get_option('onik_lens_connection_last_success', '');
        if ($last === '' || $last === false) {
            return false;
        }
        $dt = \DateTime::createFromFormat(\DateTime::ATOM, $last);
        if ($dt === false) {
            return false;
        }

        return (new \DateTime())->getTimestamp() - $dt->getTimestamp() > self::STALE_AFTER_SECONDS;
    }

    public function hasToken(): bool
    {
        return trim((string) get_option('onik_lens_jwt', '')) !== '';
    }

    public function getStatus(): array
    {
        return [
            'reason'  => get_option('onik_lens_connection_reason', ''),
            'message' => get_option('onik_lens_connection_message', ''),
        ];
    }

    public function scheduleImmediateCheck(): void
    {
        update_option('onik_lens_connection_next_check', '');
    }

    /**
     * Persist a successful response: rotate the stored token, sync tenant/site
     * from the returned identity, and record the connected verdict.
     */
    private function storeResponse(array $body, string $candidate = ''): bool
    {
        // The endpoint rotates the token on every call. Store the fresh one so
        // the next refresh presents a current token. Only now — on a confirmed
        // 200 — does the stored token change, so a failed reconnect keeps the
        // previous token intact.
        $token = $body['token'] ?? '';
        if (is_string($token) && $token !== '') {
            update_option('onik_lens_jwt', $token);
        } elseif ($candidate !== '') {
            // Accepted but not rotated: persist the candidate we just validated
            // so future refreshes still have a working token.
            update_option('onik_lens_jwt', $candidate);
        }

        // Populate the CDN path segments from the returned identity. Guard on
        // non-empty so a partial response never wipes good config.
        $shorthand = $body['tenant']['shorthand'] ?? '';
        if (is_string($shorthand) && $shorthand !== '') {
            update_option('onik_images_tenant', $shorthand);
        }
        $siteName = $body['site']['name'] ?? '';
        if (is_string($siteName) && $siteName !== '') {
            update_option('onik_images_site', $siteName);
        }

        // The image converter URL is otherwise fixed at its default
        // (https://images.onik.io/); it only changes if the connect response
        // carries an explicit override under connected.imageConverterUrl.
        $converterUrl = $body['connected']['imageConverterUrl'] ?? '';
        if (is_string($converterUrl) && $converterUrl !== '') {
            update_option('onik_images_image_converter_url', $converterUrl);
        }

        $connected = (bool) ($body['connected']['isConnected'] ?? false);
        update_option('onik_lens_connected', $connected ? '1' : '0');
        update_option('onik_lens_connection_reason', $body['connected']['reason'] ?? '');
        update_option('onik_lens_connection_message', $body['connected']['reasonMessage'] ?? '');

        // Stamp the last successful check — the staleness anchor. Reached only
        // on a real verdict-bearing response, never on a transient error.
        update_option('onik_lens_connection_last_success', (new \DateTime())->format(\DateTime::ATOM));
        update_option('onik_lens_connection_next_check', (new \DateTime('+24 hours'))->format(\DateTime::ATOM));

        return $connected;
    }

    private function storeHttpError($response): void
    {
        // Deliberately does NOT touch onik_lens_connected. This path only runs
        // when the response carried no isConnected verdict (network failure,
        // opaque error) — a real "not connected" verdict is handled by
        // storeResponse. Keeps a transient blip from deactivating a subscriber.
        update_option('onik_lens_connection_reason', 'network_error');

        $message = null;
        if (!is_wp_error($response)) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (isset($body['message']) && $body['message'] !== '') {
                $message = $body['message'];
            }
        }

        if ($message === null) {
            $message = 'Could not reach the ONIK Lens connection server. Please try again later.';
            if (is_wp_error($response)) {
                $message .= ' (' . $response->get_error_message() . ')';
            } else {
                $code = wp_remote_retrieve_response_code($response);
                if ($code !== null && $code !== false) {
                    $message .= ' (HTTP ' . $code . ')';
                }
            }
        }

        update_option('onik_lens_connection_message', $message);
        update_option('onik_lens_connection_next_check', (new \DateTime('+1 hour'))->format(\DateTime::ATOM));
    }
}
