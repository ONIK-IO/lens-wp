<?php

namespace OnikImages;

class LensActivation
{
    private const API_URL = 'https://app.onik.io/api/lens/activate';

    public function activate(): bool
    {
        $payload = [
            'wpSite'        => get_bloginfo('name'),
            'wpUrl'         => get_site_url(),
            'wpAdminEmail'  => get_option('admin_email', ''),
            'tenant'        => get_option('onik_images_tenant', ''),
            'site'          => get_option('onik_images_site', ''),
            'pluginVersion' => defined('ONIK_IMAGES_VERSION') ? ONIK_IMAGES_VERSION : '',
        ];

        $response = wp_remote_post(self::API_URL, [
            'body'    => wp_json_encode($payload),
            'headers' => ['Content-Type' => 'application/json'],
            'timeout' => 15,
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            $this->storeHttpError();
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
        return get_option('onik_lens_activated', '0') === '1';
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

        update_option('onik_lens_activation_next_check', (new \DateTime('+24 hours'))->format(\DateTime::ATOM));
    }

    private function storeHttpError(): void
    {
        update_option('onik_lens_activated', '0');
        update_option('onik_lens_activation_reason', 'network_error');
        update_option('onik_lens_activation_message', 'Could not reach the activation server. Please try again later.');
        update_option('onik_lens_activation_next_check', (new \DateTime('+1 hour'))->format(\DateTime::ATOM));
    }
}
