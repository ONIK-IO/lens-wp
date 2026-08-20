<?php

namespace OnikImages\Css;

/**
 * Handles the "Optimize this" button on the CSS Backgrounds tab.
 *
 * Scanning is a GET on the settings page because it changes nothing. Adding a
 * selector does change settings, so it posts with a nonce and redirects, the
 * same shape Connect\Gate uses.
 */
class Gate
{
    public const NONCE_ACTION = 'onik_lens_css_add_action';
    public const NONCE_FIELD  = 'onik_lens_css_add_nonce';

    public const STATUS_ADDED   = 'added';
    public const STATUS_EXISTS  = 'exists';
    public const STATUS_INVALID = 'invalid';

    /** Longest selector we will store. Real ones are far shorter. */
    private const MAX_SELECTOR_LENGTH = 500;

    public static function handleAddSelector(): void
    {
        if (!isset($_POST['onik_lens_css_add'])) {
            return;
        }
        if (!current_user_can('manage_options')) {
            return;
        }
        check_admin_referer(self::NONCE_ACTION, self::NONCE_FIELD);

        $selector = self::sanitizeSelector(
            isset($_POST['onik_lens_css_selector']) ? wp_unslash($_POST['onik_lens_css_selector']) : ''
        );
        $width  = isset($_POST['onik_lens_css_width']) ? (int) $_POST['onik_lens_css_width'] : 0;
        $source = isset($_POST['onik_lens_css_source'])
            ? esc_url_raw(wp_unslash($_POST['onik_lens_css_source']))
            : '';

        $status = self::addSelector($selector, $width);

        wp_redirect(add_query_arg([
            'page'           => 'onik_images_settings',
            'tab'            => 'css_backgrounds',
            'onik_css_url'   => rawurlencode($source),
            'onik_css_added' => $status,
        ], admin_url('options-general.php')));
        exit;
    }

    /**
     * Add one selector to onik_images_image_settings in ExternalCssUrl mode.
     *
     * Validates the whole settings blob before writing, so a bad addition can
     * never leave the option in a state the settings page would reject.
     */
    public static function addSelector(string $selector, int $width): string
    {
        if ($selector === '') {
            return self::STATUS_INVALID;
        }

        $raw      = get_option('onik_images_image_settings');
        $settings = [];

        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                return self::STATUS_INVALID;
            }
            $settings = $decoded;
        }

        if (isset($settings[$selector])) {
            return self::STATUS_EXISTS;
        }

        $config = [
            'srcSwap' => 'ExternalCssUrl',
            'quality' => 80,
            'format'  => 'auto',
        ];
        if ($width >= 1 && $width <= 10000) {
            $config['widths'] = [$width];
        }

        $settings[$selector] = $config;

        $json = wp_json_encode($settings, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if (!is_string($json)) {
            return self::STATUS_INVALID;
        }

        $validation = onik_images_validate_image_settings($json);
        if (is_wp_error($validation) || empty($validation['valid'])) {
            return self::STATUS_INVALID;
        }

        update_option('onik_images_image_settings', $json);

        return self::STATUS_ADDED;
    }

    /**
     * Selectors legitimately contain quotes and brackets, so the usual
     * text-field sanitizers are too blunt. Reject the characters that could
     * only be an attempt to break out of a CSS rule or an HTML attribute, and
     * keep the rest verbatim.
     */
    public static function sanitizeSelector(string $input): string
    {
        $selector = trim(preg_replace('/[\x00-\x1F\x7F]/', '', $input));

        if ($selector === '' || strlen($selector) > self::MAX_SELECTOR_LENGTH) {
            return '';
        }
        // `>` and `~` are combinators and must survive. `<` cannot appear in a
        // selector at all, and braces or a semicolon could only be an attempt
        // to break out of the rule we are going to write.
        if (preg_match('/[<{};]/', $selector)) {
            return '';
        }

        return $selector;
    }
}
