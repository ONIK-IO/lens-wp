<?php
/*
 * Plugin Name:       ONIK Lens
 * Plugin URI:        https://onik.io/wp/lens
 * Description:       ONIK Lens automatically optimizes images and YouTube videos. See Settings -> ONIK Lens for configuration.  
 * Version:           0.13.260318b
 * Author:            ONIK 
 * Author URI:        https://onik.io/
 * Requires at least: 6.0
 * Tested up to:      6.4
 * Requires PHP:      8.0

 */

define('ONIK_IMAGES_VERSION', '0.13.260318b');

// Require Composer autoloader
require_once __DIR__ . '/vendor/autoload.php';

use Symfony\Component\CssSelector\CssSelectorConverter;

/**
 * Plugin activation hook - sets default settings
 * Only sets defaults if settings don't already exist (never overrides user settings)
 */
function onik_images_activate($reset = false)
{
    // Set default for forbidden domains if not already set
    if (get_option('onik_images_forbidden_domains') === false || $reset) {
        update_option('onik_images_forbidden_domains', 'localhost,127.0.0.1');
    }

    if (get_option('onik_images_allow_domains') === false || $reset) {
        update_option('onik_images_allow_domains', '');
    }

    if (get_option('onik_images_tenant') === false || $reset) {
        update_option('onik_images_tenant', 'trial');
    }

    if (get_option('onik_images_site') === false || $reset) {
        update_option('onik_images_site', preg_replace('#^https?://#', '', get_site_url()));
    }
    // Set default for image converter URL if not already set
    if (get_option('onik_images_image_converter_url') === false || $reset) {
        update_option('onik_images_image_converter_url', 'https://images.onik.io/');
    }

    // Set default for enabled state if not already set
    if (get_option('onik_images_enabled') === false || $reset) {
        update_option('onik_images_enabled', '1');
    }

    // Set default for debug if not already set
    if (get_option('onik_images_debug') === false || $reset) {
        update_option('onik_images_debug', '0');
    }

    // Set default for YouTube enabled if not already set
    if (get_option('onik_images_youtube_enabled') === false || $reset) {
        update_option('onik_images_youtube_enabled', '0');
    }

    if (get_option('onik_images_image_settings') === false || $reset) {
        update_option('onik_images_image_settings', '{"img":{"quality":60, "srcSwap":"srcAndSrcSet", "format":"auto"}}');
    }
    if (get_option('onik_images_youtube_settings') === false || $reset) {
        update_option('onik_images_youtube_settings', '{"iframe[src*=\'youtube\']": {},".elementor-widget-video":{}}');
    }

    // Lens activation options
    if (get_option('onik_lens_activated') === false || $reset) {
        update_option('onik_lens_activated', '0');
    }
    if (get_option('onik_lens_activation_reason') === false || $reset) {
        update_option('onik_lens_activation_reason', '');
    }
    if (get_option('onik_lens_activation_message') === false || $reset) {
        update_option('onik_lens_activation_message', '');
    }
    // Reset to empty so next admin load triggers a re-check
    if (get_option('onik_lens_activation_next_check') === false || $reset) {
        update_option('onik_lens_activation_next_check', '');
    }

    onik_images_check_activation(true);
    
    

}
if (function_exists('register_activation_hook')) {
    register_activation_hook(__FILE__, 'onik_images_activate');
}

// Add menu page
function onik_images_add_menu_page()
{
    add_submenu_page(
        'options-general.php', // Parent slug
        'ONIK Lens', // Page title
        'ONIK Lens', // Menu title
        'manage_options', // Capability
        'onik_images_settings', // Menu slug
        'onik_images_settings_page' // Function to display the page
    );
}
add_action('admin_menu', 'onik_images_add_menu_page');

function onik_images_settings_init()
{
    register_setting('onik_images_settings', 'onik_images_enabled', [
        'sanitize_callback' => 'onik_images_sanitize_enabled'
    ]);
    register_setting('onik_images_settings', 'onik_images_tenant');
    register_setting('onik_images_settings', 'onik_images_site');
    register_setting('onik_images_settings', 'onik_images_allow_domains');
    register_setting('onik_images_settings', 'onik_images_forbidden_domains');
    register_setting('onik_images_settings', 'onik_images_image_converter_url', [
        'sanitize_callback' => 'onik_images_sanitize_image_converter_url'
    ]);
    register_setting('onik_images_settings', 'onik_images_image_settings', [
        'sanitize_callback' => 'onik_images_sanitize_image_settings'
    ]);
    register_setting('onik_images_settings', 'onik_images_regex_replace', [
        'sanitize_callback' => 'onik_images_sanitize_regex_replace'
    ]);
    register_setting('onik_images_settings', 'onik_images_preloads', [
        'sanitize_callback' => 'onik_images_sanitize_preloads'
    ]);
    register_setting('onik_images_settings', 'onik_images_script_block', [
        'sanitize_callback' => 'onik_images_sanitize_script_block'
    ]);
    register_setting('onik_images_settings', 'onik_images_debug');
    register_setting('onik_images_settings', 'onik_images_youtube_enabled');
    register_setting('onik_images_settings', 'onik_images_youtube_settings', [
        'sanitize_callback' => 'onik_images_sanitize_youtube_settings'
    ]);

    // General Settings Section
    add_settings_section(
        'onik_images_general_section',
        '',
        '',
        'onik_images_settings_general'
    );

    // Image Settings Section
    add_settings_section(
        'onik_images_image_settings_section',
        '',
        '',
        'onik_images_settings_image_settings'
    );

    if (onik_images_is_advanced_mode()) {
        // Regex Replace Section
        add_settings_section(
            'onik_images_regex_replace_section',
            '',
            '',
            'onik_images_settings_regex_replace'
        );
    }

    // Preloads Section
    add_settings_section(
        'onik_images_preloads_section',
        '',
        '',
        'onik_images_settings_preloads'
    );

    if (onik_images_is_advanced_mode()) {
        // Script Block Section
        add_settings_section(
            'onik_images_script_block_section',
            '',
            '',
            'onik_images_settings_script_block'
        );
    }

    // General Settings Fields
    add_settings_field(
        'onik_images_enabled',
        'Enable Lens Images',
        'onik_images_settings_enabled_callback',
        'onik_images_settings_general',
        'onik_images_general_section'
    );

    if (onik_images_is_advanced_mode()) {
        add_settings_field(
            'onik_images_image_converter_url',
            'Image Converter URL',
            'onik_images_settings_image_converter_url_callback',
            'onik_images_settings_general',
            'onik_images_general_section'
        );
    }

    if (onik_images_is_advanced_mode()) {
    add_settings_field(
        'onik_images_tenant',
        'Tenant:',
        'onik_images_settings_tenant_callback',
        'onik_images_settings_general',
        'onik_images_general_section'
    );

    add_settings_field(
        'onik_images_site',
        'Site:',
        'onik_images_settings_site_callback',
        'onik_images_settings_general',
        'onik_images_general_section'
    );

    
        add_settings_field(
            'onik_images_allow_domains',
            'Allow Domains',
            'onik_images_settings_allow_domains_callback',
            'onik_images_settings_general',
            'onik_images_general_section'
        );

        add_settings_field(
            'onik_images_forbidden_domains',
            'Forbidden Domains',
            'onik_images_settings_forbidden_domains_callback',
            'onik_images_settings_general',
            'onik_images_general_section'
        );

        add_settings_field(
            'onik_images_debug',
            'Debug to frontend console',
            'onik_images_settings_debug_callback',
            'onik_images_settings_general',
            'onik_images_general_section'
        );
    }

    // YouTube optimization section
    add_settings_section(
        'onik_images_youtube_section',
        'YouTube Optimization Settings',
        'onik_images_youtube_section_callback',
        'onik_images_settings_youtube_facade'
    );

    add_settings_field(
        'onik_images_youtube_enabled',
        'Enable YouTube Optimization',
        'onik_images_settings_youtube_enabled_callback',
        'onik_images_settings_youtube_facade',
        'onik_images_youtube_section'
    );

    add_settings_field(
        'onik_images_youtube_settings',
        'YouTube Settings',
        'onik_images_settings_youtube_settings_callback',
        'onik_images_settings_youtube_facade',
        'onik_images_youtube_section'
    );

    // Image Settings Fields
    add_settings_field(
        'onik_images_image_settings',
        'Image Settings',
        'onik_images_settings_image_settings_callback',
        'onik_images_settings_image_settings',
        'onik_images_image_settings_section'
    );

    if (onik_images_is_advanced_mode()) {
        // Regex Replace Fields
        add_settings_field(
            'onik_images_regex_replace',
            'Regex Replace',
            'onik_images_settings_regex_replace_callback',
            'onik_images_settings_regex_replace',
            'onik_images_regex_replace_section'
        );
    }

    // Preloads Fields
    add_settings_field(
        'onik_images_preloads',
        'Preloads',
        'onik_images_settings_preloads_callback',
        'onik_images_settings_preloads',
        'onik_images_preloads_section'
    );

    if (onik_images_is_advanced_mode()) {
        // Script Block Fields
        add_settings_field(
            'onik_images_script_block',
            'Script Block',
            'onik_images_settings_script_block_callback',
            'onik_images_settings_script_block',
            'onik_images_script_block_section'
        );
    }
}
/**
 * Check for advanced mode toggle via query string
 */
function onik_images_check_advanced_mode()
{
    if (!current_user_can('manage_options')) {
        return;
    }

    if (isset($_GET['admin'])) {
        $mode = sanitize_text_field($_GET['admin']);
        if ($mode === '1') {
            setcookie('onik_images_advanced_mode', '1', time() + 30 * DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl());
            $_COOKIE['onik_images_advanced_mode'] = '1'; // Set for current request
        } elseif ($mode === '0') {
            setcookie('onik_images_advanced_mode', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, is_ssl());
            unset($_COOKIE['onik_images_advanced_mode']); // Unset for current request
        }
    }
}
add_action('admin_init', 'onik_images_check_advanced_mode');

/**
 * Check if advanced mode is enabled
 * 
 * @return bool True if advanced mode is enabled
 */
function onik_images_is_advanced_mode()
{
    return isset($_COOKIE['onik_images_advanced_mode']) && $_COOKIE['onik_images_advanced_mode'] === '1';
}

/**
 * Register settings init to the admin_init action hook
 */
add_action('admin_init', 'onik_images_settings_init');

/**
 * Handle manual "Check Activation Now" form submission
 */
add_action('admin_init', 'onik_images_handle_activation_check');

function onik_images_handle_activation_check()
{
    if (!isset($_POST['onik_lens_activate_now'])) {
        return;
    }
    check_admin_referer('onik_lens_activate_action', 'onik_lens_activate_nonce');
    if (!current_user_can('manage_options')) {
        return;
    }
    (new \OnikImages\LensActivation())->activate();
    wp_redirect(add_query_arg([
        'page'                  => 'onik_images_settings',
        'tab'                   => 'general',
        'activation-attempted'  => '1',
    ], admin_url('options-general.php')));
    exit;
}

/**
 * Check lens activation on every admin page load (when due)
 */
add_action('admin_init', 'onik_images_check_activation');

function onik_images_check_activation()
{
    if (!is_admin() || !current_user_can('manage_options')) {
        return;
    }
    $activation = new \OnikImages\LensActivation();
    if ($activation->isCheckDue()) {
        $activation->activate();
    }
}

/**
 * Check if the current page contains YouTube videos
 * 
 * @return bool True if YouTube videos are detected, false otherwise
 */
function onik_images_has_youtube_videos()
{
    global $wp_query;

    // If we're in the admin or not on a frontend page, return false
    if (is_admin() || !$wp_query->is_main_query()) {
        return false;
    }

    // Get the current post/page content
    $content = '';
    if (is_singular()) {
        $post = get_queried_object();
        if ($post && isset($post->post_content)) {
            $content = $post->post_content;
        }
    } elseif (is_home() || is_archive()) {
        // For archive pages, we'd need to check multiple posts
        // This is a simplified check - you might want to expand this
        $posts = get_posts(array(
            'numberposts' => 10,
            'post_status' => 'publish'
        ));
        foreach ($posts as $post) {
            $content .= $post->post_content . ' ';
        }
    }

    // Also check widget areas and other content sources
    $content .= onik_images_get_widget_content();

    // Check for YouTube video patterns in the content
    return onik_images_content_has_youtube_videos($content);
}

/**
 * Get content from widgets that might contain YouTube videos
 * 
 * @return string Widget content
 */
function onik_images_get_widget_content()
{
    $widget_content = '';

    // Check common widget areas
    $widget_areas = array('sidebar-1', 'footer-1', 'footer-2', 'footer-3');

    foreach ($widget_areas as $area) {
        if (is_active_sidebar($area)) {
            ob_start();
            dynamic_sidebar($area);
            $widget_content .= ob_get_clean() . ' ';
        }
    }

    return $widget_content;
}

/**
 * Check if content contains YouTube videos by looking for YouTube URL patterns
 * 
 * @param string $content The content to check
 * @return bool True if YouTube videos are found, false otherwise
 */
function onik_images_content_has_youtube_videos($content)
{
    if (empty($content)) {
        return false;
    }

    // YouTube URL patterns to look for
    $patterns = [
        '/youtube\.com\/embed\//',
        '/youtube-nocookie\.com\/embed\//',
        '/youtube\.com\/v\//',
        '/youtu\.be\//',
        '/youtube\.com\/watch\?v=/'
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $content)) {
            return true;
        }
    }

    return false;
}

/**
 * Enqueue lite-youtube-embed assets when YouTube optimization is enabled AND videos are detected
 */
function onik_images_enqueue_youtube_assets()
{
    $youtube_enabled = get_option('onik_images_youtube_enabled');
    if (!$youtube_enabled) {
        return;
    }

    // Only enqueue assets if YouTube videos are detected on the page
    if (!onik_images_has_youtube_videos()) {
        return;
    }

    // Enqueue lite-youtube-embed CSS
    wp_enqueue_style(
        'lite-youtube-embed',
        plugin_dir_url(__FILE__) . 'assets/lite-yt-embed.css',
        array(),
        '0.3.3'
    );

    // Enqueue lite-youtube-embed JavaScript
    wp_enqueue_script(
        'lite-youtube-embed',
        plugin_dir_url(__FILE__) . 'assets/lite-yt-embed.js',
        array(),
        '0.3.3',
        true
    );
}
add_action('wp_enqueue_scripts', 'onik_images_enqueue_youtube_assets');


function onik_images_settings_page()
{
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
        return;
    }

    // Handle reset to defaults button
    if (isset($_POST['onik_images_reset'])) {
        // Verify nonce for security
        check_admin_referer('onik_images_settings-options');

        // Call activation function with reset = true
        onik_images_activate(true);

        // Redirect to settings page with success message
        $redirect_url = add_query_arg(
            array(
                'page' => 'onik_images_settings',
                'settings-updated' => 'true',
                'reset' => 'true'
            ),
            admin_url('options-general.php?page=onik_images_settings')
        );
        wp_redirect($redirect_url);
        exit;
    }

    // Get current tab
    $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
    ?>
    <div class="wrap">
        <script>document.documentElement.className += ' js-enabled';</script>
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

        <?php
        // Display reset success message
        if (isset($_GET['reset']) && $_GET['reset'] === 'true') {
            echo '<div class="notice notice-success is-dismissible"><p><strong>Settings have been reset to their default values.</strong></p></div>';
        }

        // Display any settings errors
        settings_errors('onik_images_image_settings');
        settings_errors('onik_images_regex_replace');
        settings_errors('onik_images_image_converter_url');
        settings_errors('onik_images_preloads');
        settings_errors('onik_images_script_block');

        // Activation notices
        $activation = new \OnikImages\LensActivation();
        $next_check = get_option('onik_lens_activation_next_check', '');
        $status = $activation->getStatus();

        if (isset($_GET['activation-attempted']) && $_GET['activation-attempted'] === '1') {
            if ($activation->isActivated()) {
                echo '<div class="notice notice-success is-dismissible"><p><strong>Activation successful!</strong> Your ONIK Lens account is active.</p></div>';
            } else {
                $msg = esc_html($status['message'] ?: $status['reason'] ?: 'Activation failed. Please check your credentials.');
                $clear_url = esc_url(remove_query_arg('activation-attempted'));
                echo '<div class="notice notice-error is-dismissible"><p><strong>Activation failed:</strong> ' . $msg . ' <a href="' . $clear_url . '">clear</a></p></div>';
            }
        } elseif (!$activation->isActivated() && $next_check !== '' && $next_check !== false) {
            $msg = esc_html($status['message'] ?: $status['reason'] ?: 'Your account could not be verified.');
            $nonce_field = wp_nonce_field('onik_lens_activate_action', 'onik_lens_activate_nonce', true, false);
            $action_url  = esc_url(admin_url('options-general.php?page=onik_images_settings'));
            echo '<div class="notice notice-warning is-dismissible">'
                . '<p><strong>ONIK Lens is not activated:</strong> ' . $msg . '</p>'
                . '<form method="post" action="' . $action_url . '" style="margin-bottom:10px;">'
                . '<input type="hidden" name="onik_lens_activate_now" value="1" />'
                . $nonce_field
                . '<p><input type="submit" class="button button-secondary" value="Check Activation Now" /></p>'
                . '</form>'
                . '</div>';
        }
        ?>

        <h2 class="nav-tab-wrapper">
            <a href="?page=onik_images_settings&tab=general"
                class="nav-tab <?php echo $current_tab === 'general' ? 'nav-tab-active' : ''; ?>">
                General
            </a>
            <a href="?page=onik_images_settings&tab=image_settings"
                class="nav-tab <?php echo $current_tab === 'image_settings' ? 'nav-tab-active' : ''; ?>">
                Image Settings
            </a>
            <a href="?page=onik_images_settings&tab=youtube_facade"
                class="nav-tab <?php echo $current_tab === 'youtube_facade' ? 'nav-tab-active' : ''; ?>">
                YouTube Facade
            </a>
            <a href="?page=onik_images_settings&tab=preloads"
                class="nav-tab <?php echo $current_tab === 'preloads' ? 'nav-tab-active' : ''; ?>">
                Preloads
            </a>
            <?php if (onik_images_is_advanced_mode()): ?>
                <a href="?page=onik_images_settings&tab=regex_replace"
                    class="nav-tab <?php echo $current_tab === 'regex_replace' ? 'nav-tab-active' : ''; ?>">
                    Regex Replace
                </a>

                <a href="?page=onik_images_settings&tab=script_block"
                    class="nav-tab <?php echo $current_tab === 'script_block' ? 'nav-tab-active' : ''; ?>">
                    Script Block
                </a>
            <?php endif; ?>
        </h2>

        <form action="options.php" method="post">
            <?php
            settings_fields('onik_images_settings');

            $page_slug = 'onik_images_settings_general';
            switch ($current_tab) {
                case 'image_settings':
                    $page_slug = 'onik_images_settings_image_settings';
                    break;
                case 'youtube_facade':
                    $page_slug = 'onik_images_settings_youtube_facade';
                    break;
                case 'regex_replace':
                    $page_slug = 'onik_images_settings_regex_replace';
                    break;
                case 'preloads':
                    $page_slug = 'onik_images_settings_preloads';
                    break;
                case 'script_block':
                    $page_slug = 'onik_images_settings_script_block';
                    break;
            }

            do_settings_sections($page_slug);

            // Add hidden fields to preserve settings from other tabs
            // This prevents WordPress from overwriting settings from non-active tabs with empty values
            $all_settings = [
                'onik_images_enabled',
                'onik_images_image_converter_url',
                'onik_images_tenant',
                'onik_images_site',
                'onik_images_allow_domains',
                'onik_images_forbidden_domains',
                'onik_images_debug',
                'onik_images_image_settings',
                'onik_images_youtube_enabled',
                'onik_images_youtube_settings',
                'onik_images_regex_replace',
                'onik_images_preloads',
                'onik_images_script_block'
            ];

            // Define which settings belong to which tab
            $general_settings = [
                'onik_images_enabled',
                'onik_images_tenant',
                'onik_images_site'
            ];

            if (onik_images_is_advanced_mode()) {
                $general_settings[] = 'onik_images_image_converter_url';
                $general_settings[] = 'onik_images_allow_domains';
                $general_settings[] = 'onik_images_forbidden_domains';
                $general_settings[] = 'onik_images_debug';
            }

            $tab_settings = [
                'general' => $general_settings,
                'image_settings' => ['onik_images_image_settings'],
                'youtube_facade' => ['onik_images_youtube_enabled', 'onik_images_youtube_settings'],
                'regex_replace' => ['onik_images_regex_replace'],
                'preloads' => ['onik_images_preloads'],
                'script_block' => ['onik_images_script_block']
            ];

            // Get current tab's settings
            $current_tab_settings = isset($tab_settings[$current_tab]) ? $tab_settings[$current_tab] : [];

            // Add hidden fields for settings NOT in the current tab
            foreach ($all_settings as $setting_name) {
                if (!in_array($setting_name, $current_tab_settings)) {
                    $value = get_option($setting_name);
                    // Handle array values (for complex settings like image_settings, youtube_settings, etc.)
                    if (is_array($value)) {
                        // For array settings, we need to preserve the entire structure
                        // WordPress will reconstruct the array from multiple inputs with array notation
                        foreach ($value as $index => $row) {
                            if (is_array($row)) {
                                foreach ($row as $key => $val) {
                                    echo '<input type="hidden" name="' . esc_attr($setting_name) . '[' . esc_attr($index) . '][' . esc_attr($key) . ']" value="' . esc_attr($val) . '" />';
                                }
                            } else {
                                echo '<input type="hidden" name="' . esc_attr($setting_name) . '[' . esc_attr($index) . ']" value="' . esc_attr($row) . '" />';
                            }
                        }
                    } else {
                        // For simple string values
                        echo '<input type="hidden" name="' . esc_attr($setting_name) . '" value="' . esc_attr($value) . '" />';
                    }
                }
            }
            ?>

            <p class="submit">
                <input type="submit" name="submit" id="submit" class="button button-primary" value="Save Settings">
                <input type="submit" name="onik_images_reset" id="onik_images_reset" class="button button-secondary"
                    value="Reset to Defaults" formaction="options-general.php?page=onik_images_settings"
                    onclick="return confirm('Are you sure you want to reset all settings to their default values? This action cannot be undone.');">
            </p>
        </form>


        <?php if ($current_tab === 'image_settings'): ?>
            <!-- Documentation removed as per user request -->
        <?php endif; ?>

        <?php if ($current_tab === 'regex_replace'): ?>
            <div class="onik-images-schema-info"
                style="margin-top: 20px; padding: 15px; background: #f9f9f9; border-left: 4px solid #0073aa;">
                <h3>JSON Schema Documentation</h3>

                <h4>Regex Replace</h4>
                <p>The Regex Replace field accepts a JSON array of configuration objects. Each configuration should have a
                    "targetKey" field (e.g., "rentalimage_imageloc") and optionally quality, format, width, and urlFilter. The
                    plugin will automatically build the appropriate regex patterns to find and replace image URLs in JSON-like
                    structures.</p>
                <p><strong>Example:</strong></p>
                <pre style="background: #fff; padding: 10px; border: 1px solid #ddd; overflow-x: auto;">
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                [
                                                                                                                                                                                                                                                                                                                                                                                                                                                                    </div>
                                                                                                                                                                                                                                    <?php endif; ?>

                                                                                                                                                                                                                                    <?php if ($current_tab === 'preloads'): ?>
                                                                                                                                                                                                                                                                                                                                                                                                                                                                    <!-- Documentation removed as per user request -->
                                                                                                                                                                                                                                    <?php endif; ?>

                                                                                                                                                                                                                                    <?php if ($current_tab === 'script_block'): ?>
                                                                                                                                                                                                                                                                                                                                                                                                                                                                    <div class="onik-images-schema-info"
                                                                                                                                                                                                                                                                                                                                                                                                                                                                        style="margin-top: 20px; padding: 15px; background: #f9f9f9; border-left: 4px solid #0073aa;">
                                                                                                                                                                                                                                                                                                                                                                                                                                                                        <h3>JSON Schema Documentation</h3>

                                                                                                                                                                                                                                                                                                                                                                                                                                                                        <h4>Script Block</h4>
                                                                                                                                                                                                                                                                                                                                                                                                                                                                        <p>The Script Block field accepts a JSON array of configuration objects. Each configuration should have a
                                                                                                                                                                                                                                                                                                                                                                                                                                                                            "urlPattern" field (regex pattern) and optionally a "urlFilter" field for additional URL filtering.</p>
                                                                                                                                                                                                                                                                                                                                                                                                                                                                        <p><strong>Example:</strong></p>
                                                                                                                                                                                                                                                                                                                                                                                                                                                                        <pre style="background: #fff; padding: 10px; border: 1px solid #ddd; overflow-x: auto;">
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                [
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    {
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        "urlPattern": "#/products/.*#",
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        "urlFilter": "#/featured/.*#"
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    },
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    {
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        "urlPattern": "#/blog/.*#"
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    }
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                ]</pre>
            </div>
        <?php endif; ?>

        <?php if ($current_tab === 'youtube_facade'): ?>
            <!-- Documentation removed as per user request -->
        <?php endif; ?>
    </div>

    <style>
        .nav-tab-wrapper {
            margin-bottom: 20px;
        }

        .tab-content {
            margin-top: 20px;
        }

        .onik-images-schema-info {
            margin-top: 20px;
            padding: 15px;
            background: #f9f9f9;
            border-left: 4px solid #0073aa;
        }

        /* Hide fields that don't belong to the current tab */
        .form-table tr[data-tab] {
            display: none;
        }

        .form-table tr[data-tab="<?php echo $current_tab; ?>"] {
            display: table-row;
        }

        /* Hide section headers that don't belong to the current tab */
        .form-table tr[data-section] {
            display: none;
        }

        .form-table tr[data-section="<?php echo $current_tab; ?>"] {
            display: table-row;
        }

        /* Always show the submit button */
        .submit {
            display: block !important;
        }

        /* Fallback for when JavaScript is disabled - show all fields */
        .js-enabled .form-table tr[data-tab] {
            display: none;
        }

        .js-enabled .form-table tr[data-tab="<?php echo $current_tab; ?>"] {
            display: table-row;
        }

        .js-enabled .form-table tr[data-section] {
            display: none;
        }

        .js-enabled .form-table tr[data-section="<?php echo $current_tab; ?>"] {
            display: table-row;
        }
    </style>

    <script>
        jQuery(document).ready(function ($) {
            // Add data attributes to table rows for tab id entification
            $('.form-table t r').each(function () {
                var $row = $(this);
                var$input = $row.find('input, textarea');
                var $th = $row.find('th');

                // Check if this is a field row
                if ($input.length) {
                    var fieldName = $input.attr('name');
                    if (fieldName) {
                        // Map field names to tabs
                        var tab = '';
                        if (['onik_images_tenant', 'onik_images_site', 'onik_images_allow_domains', 'onik_images_forbidden_domains', 'onik_images_debug'].indexOf(fieldName) !== -1) {
                            tab = 'general';
                        } else if (['onik_images_enabled', 'onik_images_image_converter_url', 'onik_images_image_settings'].indexOf(fieldName) !== -1) {
                            tab = 'image_settings';
                        } else if (['onik_images_youtube_enabled', 'onik_images_youtube_settings'].indexOf(fieldName) !== -1) {
                            tab = 'youtube_facade';
                        } else if (fieldName === 'onik_images_regex_replace') {
                            tab = 'regex_replace';
                        } else if (fieldName === 'onik_images_preloads') {
                            tab = 'preloads';
                        } else if (fieldName === 'onik_images_script_block') {
                            tab = 'script_block';
                        }
                        if (tab) {
                            $row.attr('data-tab', tab);
                        }
                    }
                }

                // Check if this is a section header row (th with colspan or specific text)
                if ($th.length && ($th.attr('colspan') || $th.hasClass('section-title'))) {
                    var sectionText = $th.text().trim();
                    var tab = '';

                    // Map section text to tabs
                    if (sectionText === 'General Settings') {
                        tab = 'general';
                    } else if (sectionText === 'Image Settings') {
                        tab = 'image_settings';
                    } else if (sectionText === 'YouTube Optimization Settings') {
                        tab = 'youtube_facade';
                    } else if (sectionText === 'Regex Replace') {
                        tab = 'regex_replace';
                    } else if (sectionText === 'Preloads') {
                        tab = 'preloads';
                    } else if (sectionText === 'Script Block') {
                        tab = 'script_block';
                    }

                    if (tab) {
                        $row.attr('data-section', tab);
                    }
                }
            });

            // Show only the current tab's content
            var currentTab = '<?php echo $current_tab; ?>';
            if (currentTab && currentTab !== '') {
                console.log('Current tab:', currentTab);
                console.log('Fields found:', $('.form-table tr[data-tab]').length);
                console.log('Sections found:', $('.form-table tr[data-section]').length);

                $('.form-table tr[data-tab]').hide();
                $('.form-table tr[data-tab="' + currentTab + '"]').show();
                $('.form-table tr[data-section]').hide();
                $('.form-table tr[data-section="' + currentTab + '"]').show();
            }

            // Handle tab clicks
            $('.nav-tab').on('click', function (e) {
                e.preventDefault();
                var tab = $(this).attr('href').split('tab=')[1];
                if (tab) {
                    window.location.href = '?page=onik_images_settings&tab=' + tab;
                }
            });
        });
    </script>

    <noscript>
        <style>
            .form-table tr[data-tab] {
                display: table-row !important;
            }

            .form-table tr[data-section] {
                display: table-row !important;
            }
        </style>
    </noscript>


    <?php
}

function onik_images_account_section_callback()
{
    echo '<p>Configure your ONIK Lens account credentials.</p>';

    $activation = new \OnikImages\LensActivation();
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

function onik_images_settings_image_converter_url_callback()
{
    onik_images_addTextOption('onik_images_image_converter_url');
    echo '<p>Enter the base URL for the ONIK image converter service. This field is required when ONIK Images is enabled and must end with a trailing slash (/).</p>';
}



function onik_images_settings_enabled_callback()
{
    onik_images_addCheckboxOption('onik_images_enabled');
    echo '<p style="">When unchecked, the plugin will have no effect on the front end</p>';
    if (onik_images_is_advanced_mode() === false) {
        return;
    }
    $debug_rows = [
        'onik_lens_activated'            => get_option('onik_lens_activated', ''),
        'onik_lens_activation_reason'    => get_option('onik_lens_activation_reason', ''),
        'onik_lens_activation_message'   => get_option('onik_lens_activation_message', ''),
        'onik_lens_activation_next_check' => get_option('onik_lens_activation_next_check', ''),
    ];

    echo '<table class="form-table" role="presentation"><tbody>';
    foreach ($debug_rows as $key => $value) {
        echo '<tr>';
        echo '<th scope="row">' . esc_html($key) . '</th>';
        echo '<td>' . esc_html($value !== '' ? $value : '(empty)') . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
    
}

function onik_images_settings_tenant_callback()
{
    
    onik_images_addTextOption('onik_images_tenant');
}

function onik_images_settings_site_callback()
{
    onik_images_addTextOption('onik_images_site');
}

function onik_images_settings_allow_domains_callback()
{
    onik_images_addTextOption('onik_images_allow_domains');
    echo '<p>Enter the domains hosting images you want ONIK to manage. Separate multiple domains with a comma. <br />If you leave this blank, ONIK will include images from all domains.</p>';
}

function onik_images_settings_forbidden_domains_callback()
{
    onik_images_addTextOption('onik_images_forbidden_domains');
    echo '<p>Enter domains that should be excluded from ONIK processing. Separate multiple domains with a comma. <br />Default: localhost,127.0.0.1 (when empty or not set).</p>';
}

function onik_images_settings_debug_callback()
{
    onik_images_addCheckboxOption('onik_images_debug');
}

function onik_images_settings_image_settings_callback()
{
    $setting = get_option('onik_images_image_settings');
    $converter = new \OnikImages\SettingsConverter();
    $tableData = $converter->jsonToTable($setting ?: '{}');

    // Ensure we have at least one row if empty, or just let it be empty
    ?>
    <style>
        #onik_images_image_settings_table {
            width: 100%;
            table-layout: fixed;
            /* Optional: helps with long content */
        }

        #onik_images_image_settings_table th,
        #onik_images_image_settings_table td {
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        /* Adjust column widths if necessary */
        .col-selector {
            width: 15%;
        }

        .col-widths {
            width: 10%;
        }

        /* ... other columns ... */
    </style>
    <div class="wrap">
        <table class="widefat fixed" id="onik_images_image_settings_table">
            <thead>
                <tr>
                    <th style="width: 15%;">Selector</th>
                    <th style="width: 10%;">Widths</th>
                    <th style="width: 5%;">Quality</th>
                    <th style="width: 7%;">Loading</th>
                    <th style="width: 10%;">Sizes</th>
                    <th style="width: 8%;">Fetch Priority</th>
                    <th style="width: 7%;">Decoding</th>
                    <th style="width: 7%;">Format</th>
                    <th style="width: 8%;">SrcSwap</th>
                    <th style="width: 6%;">Set Width</th>
                    <th style="width: 6%;">Set Height</th>
                    <th style="width: 6%;">Lazy Load After</th>
                    <th style="width: 10%;">Actions</th>
                    <th style="width: 10%;">Actions</th>
                </tr>
            </thead>
            <tbody id="onik_images_image_settings_tbody">
                <?php if (empty($tableData)): ?>
                    <tr class="no-items">
                        <td colspan="12">No settings found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($tableData as $index => $row): ?>
                        <tr>
                            <td class="col-selector">
                                <span class="display-value"><?php echo esc_html($row['selector']); ?></span>
                                <input type="hidden" name="onik_images_image_settings[<?php echo $index; ?>][selector]"
                                    value="<?php echo esc_attr($row['selector']); ?>" />
                            </td>
                            <td class="col-widths">
                                <span class="display-value"><?php echo esc_html($row['widths']); ?></span>
                                <input type="hidden" name="onik_images_image_settings[<?php echo $index; ?>][widths]"
                                    value="<?php echo esc_attr($row['widths']); ?>" />
                            </td>
                            <td class="col-quality">
                                <span class="display-value"><?php echo esc_html($row['quality']); ?></span>
                                <input type="hidden" name="onik_images_image_settings[<?php echo $index; ?>][quality]"
                                    value="<?php echo esc_attr($row['quality']); ?>" />
                            </td>
                            <td class="col-loading">
                                <span class="display-value"><?php echo esc_html($row['loading']); ?></span>
                                <input type="hidden" name="onik_images_image_settings[<?php echo $index; ?>][loading]"
                                    value="<?php echo esc_attr($row['loading']); ?>" />
                            </td>
                            <td class="col-sizes">
                                <span class="display-value"><?php echo esc_html($row['sizes']); ?></span>
                                <input type="hidden" name="onik_images_image_settings[<?php echo $index; ?>][sizes]"
                                    value="<?php echo esc_attr($row['sizes']); ?>" />
                            </td>
                            <td class="col-fetchpriority">
                                <span class="display-value"><?php echo esc_html($row['fetchpriority']); ?></span>
                                <input type="hidden" name="onik_images_image_settings[<?php echo $index; ?>][fetchpriority]"
                                    value="<?php echo esc_attr($row['fetchpriority']); ?>" />
                            </td>
                            <td class="col-decoding">
                                <span class="display-value"><?php echo esc_html($row['decoding']); ?></span>
                                <input type="hidden" name="onik_images_image_settings[<?php echo $index; ?>][decoding]"
                                    value="<?php echo esc_attr($row['decoding']); ?>" />
                            </td>
                            <td class="col-format">
                                <span class="display-value"><?php echo esc_html($row['format']); ?></span>
                                <input type="hidden" name="onik_images_image_settings[<?php echo $index; ?>][format]"
                                    value="<?php echo esc_attr($row['format']); ?>" />
                            </td>
                            <td class="col-srcSwap">
                                <span class="display-value"><?php echo esc_html($row['srcSwap']); ?></span>
                                <input type="hidden" name="onik_images_image_settings[<?php echo $index; ?>][srcSwap]"
                                    value="<?php echo esc_attr($row['srcSwap']); ?>" />
                            </td>
                            <td class="col-setWidth">
                                <span class="display-value"><?php echo esc_html($row['setWidth']); ?></span>
                                <input type="hidden" name="onik_images_image_settings[<?php echo $index; ?>][setWidth]"
                                    value="<?php echo esc_attr($row['setWidth']); ?>" />
                            </td>
                            <td class="col-setHeight">
                                <span class="display-value"><?php echo esc_html($row['setHeight']); ?></span>
                                <input type="hidden" name="onik_images_image_settings[<?php echo $index; ?>][setHeight]"
                                    value="<?php echo esc_attr($row['setHeight']); ?>" />
                            </td>
                            <td class="col-lazyLoadAfter">
                                <span class="display-value"><?php echo esc_html($row['lazyLoadAfter']); ?></span>
                                <input type="hidden" name="onik_images_image_settings[<?php echo $index; ?>][lazyLoadAfter]"
                                    value="<?php echo esc_attr($row['lazyLoadAfter']); ?>" />
                            </td>
                            <td>
                                <div style="display:flex; gap:5px;">
                                    <button type="button" class="button edit-row" title="Edit">✎</button>
                                    <button type="button" class="button move-up" title="Move Up">↑</button>
                                    <button type="button" class="button move-down" title="Move Down">↓</button>
                                    <button type="button" class="button delete-row" title="Delete">×</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <p>
            <button type="button" class="button" id="add-row">Add Row</button>
        </p>
    </div>

    <div id="onik-image-settings-modal"
        style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:100000;">
        <div
            style="background:#fff; width:800px; margin:100px auto; padding:20px; border-radius:5px; box-shadow:0 0 10px rgba(0,0,0,0.3); max-height: 80vh; overflow-y: auto;">
            <h2 id="onik-modal-title" style="margin-top:0;">Edit Image Setting</h2>
            <div id="onik-modal-form">
                <input type="hidden" id="onik-modal-row-index" value="">
                <table class="form-table">
                    <tr>
                        <th><label for="onik-modal-selector">Selector</label></th>
                        <td>
                            <input type="text" id="onik-modal-selector" class="regular-text" style="width:100%;">
                            <p class="description">CSS selector to target images.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="onik-modal-widths">Widths</label></th>
                        <td>
                            <input type="text" id="onik-modal-widths" class="regular-text" style="width:100%;"
                                placeholder="e.g. 300, 600, 900">
                            <p class="description">Array of integers between 1-10000 representing image widths in pixels. If
                                not provided, the width will be extracted from the image element's width attribute.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="onik-modal-quality">Quality</label></th>
                        <td>
                            <input type="number" id="onik-modal-quality" class="small-text" min="1" max="100">
                            <p class="description">Integer between 1-100 for image quality percentage (default: 80).</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="onik-modal-loading">Loading</label></th>
                        <td>
                            <select id="onik-modal-loading">
                                <option value="">Default</option>
                                <option value="lazy">Lazy</option>
                                <option value="eager">Eager</option>
                            </select>
                            <p class="description">"lazy", "eager", or empty for browser default.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="onik-modal-sizes">Sizes</label></th>
                        <td>
                            <input type="text" id="onik-modal-sizes" class="regular-text" style="width:100%;">
                            <p class="description">String for CSS sizes attribute value.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="onik-modal-fetchpriority">Fetch Priority</label></th>
                        <td>
                            <select id="onik-modal-fetchpriority">
                                <option value="">Default</option>
                                <option value="high">High</option>
                                <option value="low">Low</option>
                                <option value="auto">Auto</option>
                            </select>
                            <p class="description">"high", "low", "auto", or empty for no attribute.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="onik-modal-decoding">Decoding</label></th>
                        <td>
                            <select id="onik-modal-decoding">
                                <option value="">Default</option>
                                <option value="sync">Sync</option>
                                <option value="async">Async</option>
                                <option value="auto">Auto</option>
                            </select>
                            <p class="description">"sync", "async", "auto", or empty for no attribute.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="onik-modal-format">Format</label></th>
                        <td>
                            <select id="onik-modal-format">
                                <option value="">Default</option>
                                <option value="auto">Auto</option>
                                <option value="jpg">JPG</option>
                                <option value="jpeg">JPEG</option>
                                <option value="png">PNG</option>
                                <option value="webp">WebP</option>
                                <option value="avif">AVIF</option>
                            </select>
                            <p class="description">"auto", "jpg", "jpeg", "png", "gif", "avif", "webp", or empty for no format
                                specification (default: "auto").</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="onik-modal-srcSwap">SrcSwap</label></th>
                        <td>
                            <select id="onik-modal-srcSwap">
                                <option value="">Default</option>
                                <option value="srcSet">srcSet</option>
                                <option value="src">src</option>
                                <option value="srcAndSrcSet">srcAndSrcSet</option>
                                <option value="InlineStyleUrl">InlineStyleUrl</option>
                            </select>
                            <p class="description">"srcSet", "src", "srcAndSrcSet", or "InlineStyleUrl" to control which
                                image attributes to
                                swap (default: "srcSet"). Use "InlineStyleUrl" for inline CSS background-image URLs.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="onik-modal-setWidth">Set Width</label></th>
                        <td>
                            <input type="number" id="onik-modal-setWidth" class="small-text" min="1">
                            <p class="description">Fixed width attribute to add to the image tag (null or positive integer).
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="onik-modal-setHeight">Set Height</label></th>
                        <td>
                            <input type="number" id="onik-modal-setHeight" class="small-text" min="1">
                            <p class="description">Fixed height attribute to add to the image tag (null or positive
                                integer).</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="onik-modal-lazyLoadAfter">Lazy Load After</label></th>
                        <td>
                            <input type="number" id="onik-modal-lazyLoadAfter" class="small-text" min="0">
                            <p class="description">Number of images to process before enabling lazy loading (default: 0).
                            </p>
                        </td>
                    </tr>
                </table>
                <p class="submit" style="text-align:right; margin-top:20px;">
                    <button type="button" class="button" id="onik-modal-cancel">Cancel</button>
                    <button type="button" class="button button-primary" id="onik-modal-save">Save</button>
                </p>
            </div>
        </div>
    </div>

    <?php if (onik_images_is_advanced_mode()): ?>
        <div style="margin-top: 10px;">
            <a href="#" id="onik-debug-json-link" style="text-decoration: none; border-bottom: 1px dashed #0073aa;">Debug
                JSON</a>
            <div id="onik-debug-json-popup"
                style="display: none; position: absolute; background: #fff; border: 1px solid #ccc; padding: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); z-index: 9999; max-width: 600px; max-height: 400px; overflow: auto;">
                <pre style="margin: 0; font-family: monospace; white-space: pre-wrap;"></pre>
            </div>
        </div>

        <div style="margin-top: 20px; border-top: 1px solid #ccc; padding-top: 10px;">
            <h3>Import / Export Settings</h3>
            <p>Paste the JSON settings string below to import settings. This will replace the current table contents.</p>
            <textarea id="onik-import-settings-json" rows="5" style="width: 100%; font-family: monospace;"><?php
            // Format current settings as pretty JSON for the textarea
            if ($setting && $setting !== '{}') {
                $decoded = json_decode($setting, true);
                if ($decoded !== null) {
                    echo esc_textarea(json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                }
            }
            ?></textarea>
            <p><button type="button" class="button" id="onik-import-settings-btn">Import</button></p>
        </div>
    <?php endif; ?>

    <script>
        jQuery(document).ready(function ($) {
            var $table = $('#onik_images_image_settings_table tbody');
            var $modal = $('#onik-image-settings-modal');
            var $modalForm = $('#onik-modal-form');
            var $modalTitle = $('#onik-modal-title');
            var $rowIndexInput = $('#onik-modal-row-index');

            // Move modal to body to avoid z-index/positioning issues
            $('body').append($modal);

            function updateRowIndices() {
                $table.find('tr').each(function (index) {
                    $(this).find('input[type="hidden"]').each(function () {
                        var name = $(this).attr('name');
                        if (name) {
                            var newName = name.replace(/\[\d+\]/, '[' + index + ']');
                            $(this).attr('name', newName);
                        }
                    });
                });
            }

            function openModal(row) {
                // Reset form fields manually since it's a div now
                $modalForm.find('input[type="text"], input[type="number"]').val('');
                $modalForm.find('select').val('');
                $rowIndexInput.val('');
                if (row) {
                    $modalTitle.text('Edit Image Setting');
                    var index = $table.find('tr').index(row);
                    $rowIndexInput.val(index);

                    // Populate form
                    $('#onik-modal-selector').val(row.find('.col-selector input').val());
                    $('#onik-modal-widths').val(row.find('.col-widths input').val());
                    $('#onik-modal-quality').val(row.find('.col-quality input').val());
                    $('#onik-modal-loading').val(row.find('.col-loading input').val());
                    $('#onik-modal-sizes').val(row.find('.col-sizes input').val());
                    $('#onik-modal-fetchpriority').val(row.find('.col-fetchpriority input').val());
                    $('#onik-modal-decoding').val(row.find('.col-decoding input').val());
                    $('#onik-modal-format').val(row.find('.col-format input').val());
                    $('#onik-modal-srcSwap').val(row.find('.col-srcSwap input').val());
                    $('#onik-modal-setWidth').val(row.find('.col-setWidth input').val());
                    $('#onik-modal-setHeight').val(row.find('.col-setHeight input').val());
                    $('#onik-modal-lazyLoadAfter').val(row.find('.col-lazyLoadAfter input').val());
                } else {
                    $modalTitle.text('Add Image Setting');
                    $rowIndexInput.val('');
                }
                $modal.show();
            }

            function closeModal() {
                $modal.hide();
            }

            function renderRow(index, data) {
                var rowHtml = '<tr>';
                $.each(data, function (key, value) {
                    var displayValue = value;
                    if (key === 'widths' && Array.isArray(value)) {
                        displayValue = value.join(', ');
                    }
                    rowHtml += '<td class="col-' + key + '"><span class="display-value">' + $('<div>').text(displayValue).html() + '</span><input type="hidden" name="onik_images_image_settings[' + index + '][' + key + ']" value="' + $('<div>').text(displayValue).html() + '" /></td>';
                });
                rowHtml += '<td><div style="display:flex; gap:5px;"><button type="button" class="button edit-row" title="Edit">✎</button><button type="button" class="button move-up" title="Move Up">↑</button><button type="button" class="button move-down" title="Move Down">↓</button><button type="button" class="button delete-row" title="Delete">×</button></div></td></tr>';
                return rowHtml;
            }

            $('#add-row').on('click', function () {
                openModal(null);
            });

            $table.on('click', '.edit-row', function (e) {
                e.preventDefault();
                console.log('Edit row clicked');
                openModal($(this).closest('tr'));
            });

            $('#onik-modal-cancel').on('click', closeModal);

            $('#onik-modal-save').on('click', function () {
                var index = $rowIndexInput.val();
                var data = {
                    selector: $('#onik-modal-selector').val(),
                    widths: $('#onik-modal-widths').val(),
                    quality: $('#onik-modal-quality').val(),
                    loading: $('#onik-modal-loading').val(),
                    sizes: $('#onik-modal-sizes').val(),
                    fetchpriority: $('#onik-modal-fetchpriority').val(),
                    decoding: $('#onik-modal-decoding').val(),
                    format: $('#onik-modal-format').val(),
                    srcSwap: $('#onik-modal-srcSwap').val(),
                    setWidth: $('#onik-modal-setWidth').val(),
                    setHeight: $('#onik-modal-setHeight').val(),
                    lazyLoadAfter: $('#onik-modal-lazyLoadAfter').val()
                };

                if (!data.selector) {
                    alert('Selector is required.');
                    return;
                }

                if (index === '') {
                    // Add new row
                    if ($table.find('.no-items').length) {
                        $table.empty();
                    }
                    index = $table.find('tr').length;
                    $table.append(renderRow(index, data));
                } else {
                    // Update existing row
                    var $row = $table.find('tr').eq(index);
                    $.each(data, function (key, value) {
                        var $cell = $row.find('.col-' + key);
                        $cell.find('.display-value').text(value);
                        $cell.find('input').val(value);
                    });
                }

                closeModal();
            });

            $table.on('click', '.delete-row', function () {
                $(this).closest('tr').remove();
                if ($table.find('tr').length === 0) {
                    $table.append('<tr class="no-items"><td colspan="12">No settings found.</td></tr>');
                } else {
                    updateRowIndices();
                }
            });

            $table.on('click', '.move-up', function () {
                var $row = $(this).closest('tr');
                if ($row.prev().length) {
                    $row.insertBefore($row.prev());
                    updateRowIndices();
                }
            });

            $table.on('click', '.move-down', function () {
                var $row = $(this).closest('tr');
                if ($row.next().length) {
                    $row.insertAfter($row.next());
                    updateRowIndices();
                }
            });

            // Import Settings Logic
            $('#onik-import-settings-btn').on('click', function () {
                var jsonStr = $('#onik-import-settings-json').val();
                if (!jsonStr) {
                    alert('Please paste JSON settings to import.');
                    return;
                }

                try {
                    var importedData = JSON.parse(jsonStr);

                    // Validate structure (basic check)
                    if (typeof importedData !== 'object' || importedData === null) {
                        throw new Error('Invalid JSON format');
                    }

                    // Clear existing table
                    $table.empty();

                    var index = 0;
                    // Handle both array format (from settings) and object format (from debug)
                    // Debug format: { "selector": { config... }, ... }
                    // Settings format might be different, but let's assume we want to support the debug format primarily as requested

                    // Check if it's the debug format (object with selectors as keys)
                    // or potentially an array of setting objects

                    $.each(importedData, function (key, config) {
                        // If key is a selector (string) and config is an object
                        var rowData = {
                            selector: key,
                            widths: '',
                            quality: '',
                            loading: '',
                            sizes: '',
                            fetchpriority: '',
                            decoding: '',
                            format: '',
                            srcSwap: '',
                            setWidth: '',
                            setHeight: '',
                            lazyLoadAfter: ''
                        };

                        // Map config values to rowData
                        if (config.widths && Array.isArray(config.widths)) {
                            rowData.widths = config.widths.join(', ');
                        }

                        var fields = ['quality', 'loading', 'sizes', 'fetchpriority', 'decoding', 'format', 'srcSwap', 'setWidth', 'setHeight', 'lazyLoadAfter'];
                        fields.forEach(function (field) {
                            if (config[field] !== undefined && config[field] !== null) {
                                rowData[field] = config[field];
                            }
                        });

                        $table.append(renderRow(index, rowData));
                        index++;
                    });

                    if (index === 0) {
                        $table.append('<tr class="no-items"><td colspan="12">No settings found.</td></tr>');
                    }

                    // Clear the textarea
                    $('#onik-import-settings-json').val('');
                    alert('Settings imported successfully! Click "Save Settings" to persist changes.');

                } catch (e) {
                    alert('Error importing settings: ' + e.message);
                    console.error(e);
                }
            });

            // Debug JSON Popup Logic
            var $debugLink = $('#onik-debug-json-link');
            var $popup = $('#onik-debug-json-popup');
            var $pre = $popup.find('pre');

            $debugLink.on('mouseenter', function (e) {
                var data = {};

                $table.find('tr').each(function () {
                    var $row = $(this);
                    if ($row.hasClass('no-items')) return;

                    var selector = $row.find('input[name*="[selector]"]').val();
                    if (!selector) return;

                    var config = {};

                    // Widths
                    var widthsStr = $row.find('input[name*="[widths]"]').val();
                    if (widthsStr) {
                        var widths = widthsStr.split(',').map(function (w) { return parseInt(w.trim(), 10); }).filter(function (w) { return !isNaN(w) && w > 0; });
                        if (widths.length > 0) {
                            config.widths = widths;
                        }
                    }

                    // Other fields
                    var fields = ['quality', 'loading', 'sizes', 'fetchpriority', 'decoding', 'format', 'srcSwap', 'setWidth', 'setHeight', 'lazyLoadAfter'];
                    fields.forEach(function (field) {
                        var $input = $row.find('[name*="[' + field + ']"]');
                        var val = $input.val();
                        if (val !== '') {
                            if (['quality', 'setWidth', 'setHeight', 'lazyLoadAfter'].indexOf(field) !== -1) {
                                val = parseInt(val, 10);
                            }
                            config[field] = val;
                        }
                    });

                    data[selector] = config;
                });

                $pre.text(JSON.stringify(data, null, 4));

                // Position popup near the link
                var offset = $debugLink.offset();
                $popup.css({
                    top: offset.top + 20,
                    left: offset.left
                }).show();
            });

            $debugLink.on('mouseleave', function () {
                $popup.hide();
            });
        });
    </script>
    <?php
}

function onik_images_youtube_section_callback()
{
    echo '<p>Configure YouTube optimization settings below. This feature replaces standard YouTube embeds with lightweight lite-youtube-embed components for faster page loading.</p>';
}

function onik_images_settings_youtube_enabled_callback()
{
    onik_images_addCheckboxOption('onik_images_youtube_enabled');
    echo '<p style="font-weight: bold; color: #0073aa;">When unchecked, the plugin will have no effect on YouTube content.</p>';
}

function onik_images_settings_youtube_settings_callback()
{
    $setting = get_option('onik_images_youtube_settings');
    $converter = new \OnikImages\SettingsConverter();
    $tableData = $converter->youtubeJsonToTable($setting ?: '{}');

    ?>
    <style>
        #onik_images_youtube_settings_table {
            width: 100%;
            table-layout: fixed;
        }

        #onik_images_youtube_settings_table th,
        #onik_images_youtube_settings_table td {
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .col-selector {
            width: 20%;
        }

        .col-playlabel {
            width: 15%;
        }

        .col-title {
            width: 15%;
        }

        .col-params {
            width: 20%;
        }

        .col-js_api {
            width: 5%;
        }

        .col-style {
            width: 15%;
        }

        .col-actions {
            width: 10%;
        }
    </style>
    <div class="wrap">
        <table class="widefat fixed" id="onik_images_youtube_settings_table">
            <thead>
                <tr>
                    <th class="col-selector">Selector</th>
                    <th class="col-playlabel">Play Label</th>
                    <th class="col-title">Title</th>
                    <th class="col-params">Params</th>
                    <th class="col-js_api">JS API</th>
                    <th class="col-style">Style</th>
                    <th class="col-actions">Actions</th>
                </tr>
            </thead>
            <tbody id="onik_images_youtube_settings_tbody">
                <?php if (empty($tableData)): ?>
                    <tr class="no-items">
                        <td colspan="7">No settings found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($tableData as $index => $row): ?>
                        <tr>
                            <td class="col-selector">
                                <span class="display-value"><?php echo esc_html($row['selector']); ?></span>
                                <input type="hidden" name="onik_images_youtube_settings[<?php echo $index; ?>][selector]"
                                    value="<?php echo esc_attr($row['selector']); ?>" />
                            </td>
                            <td class="col-playlabel">
                                <span class="display-value"><?php echo esc_html($row['playlabel']); ?></span>
                                <input type="hidden" name="onik_images_youtube_settings[<?php echo $index; ?>][playlabel]"
                                    value="<?php echo esc_attr($row['playlabel']); ?>" />
                            </td>
                            <td class="col-title">
                                <span class="display-value"><?php echo esc_html($row['title']); ?></span>
                                <input type="hidden" name="onik_images_youtube_settings[<?php echo $index; ?>][title]"
                                    value="<?php echo esc_attr($row['title']); ?>" />
                            </td>
                            <td class="col-params">
                                <span class="display-value"><?php echo esc_html($row['params']); ?></span>
                                <input type="hidden" name="onik_images_youtube_settings[<?php echo $index; ?>][params]"
                                    value="<?php echo esc_attr($row['params']); ?>" />
                            </td>
                            <td class="col-js_api">
                                <span class="display-value"><?php echo $row['js_api'] ? 'Yes' : 'No'; ?></span>
                                <input type="hidden" name="onik_images_youtube_settings[<?php echo $index; ?>][js_api]"
                                    value="<?php echo $row['js_api'] ? '1' : '0'; ?>" />
                            </td>
                            <td class="col-style">
                                <span class="display-value"><?php echo esc_html($row['style']); ?></span>
                                <input type="hidden" name="onik_images_youtube_settings[<?php echo $index; ?>][style]"
                                    value="<?php echo esc_attr($row['style']); ?>" />
                            </td>
                            <td>
                                <div style="display:flex; gap:5px;">
                                    <button type="button" class="button edit-row" title="Edit">✎</button>
                                    <button type="button" class="button move-up" title="Move Up">↑</button>
                                    <button type="button" class="button move-down" title="Move Down">↓</button>
                                    <button type="button" class="button delete-row" title="Delete">×</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <p>
            <button type="button" class="button" id="add-youtube-row">Add Row</button>
        </p>
    </div>

    <div id="onik-youtube-settings-modal"
        style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:100000;">
        <div
            style="background:#fff; width:800px; margin:100px auto; padding:20px; border-radius:5px; box-shadow:0 0 10px rgba(0,0,0,0.3); max-height: 80vh; overflow-y: auto;">
            <h2 id="onik-youtube-modal-title" style="margin-top:0;">Edit YouTube Setting</h2>
            <div id="onik-youtube-modal-form">
                <input type="hidden" id="onik-youtube-modal-row-index" value="">
                <table class="form-table">
                    <tr>
                        <th><label for="onik-youtube-modal-selector">Selector</label></th>
                        <td>
                            <input type="text" id="onik-youtube-modal-selector" class="regular-text" style="width:100%;">
                            <p class="description">CSS selector to target YouTube videos (e.g.,
                                <code>iframe[src*='youtube']</code>).
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="onik-youtube-modal-playlabel">Play Label</label></th>
                        <td>
                            <input type="text" id="onik-youtube-modal-playlabel" class="regular-text" style="width:100%;"
                                placeholder="Play: {video_id}">
                            <p class="description">String for the play button label (default: "Play: {video_id}").</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="onik-youtube-modal-title">Title</label></th>
                        <td>
                            <input type="text" id="onik-youtube-modal-title" class="regular-text" style="width:100%;">
                            <p class="description">String for the video title attribute.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="onik-youtube-modal-params">Params</label></th>
                        <td>
                            <input type="text" id="onik-youtube-modal-params" class="regular-text" style="width:100%;"
                                placeholder="controls=1&autoplay=0">
                            <p class="description">String for YouTube player parameters (e.g.,
                                "controls=0&start=10&end=30&modestbranding=2&rel=0").</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="onik-youtube-modal-js_api">JS API</label></th>
                        <td>
                            <select id="onik-youtube-modal-js_api">
                                <option value="0">No</option>
                                <option value="1">Yes</option>
                            </select>
                            <p class="description">Enable YouTube IFrame Player API (default: false).</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="onik-youtube-modal-style">Style</label></th>
                        <td>
                            <input type="text" id="onik-youtube-modal-style" class="regular-text" style="width:100%;">
                            <p class="description">String containing CSS styles to append to the lite-youtube element's
                                style attribute.</p>
                        </td>
                    </tr>
                </table>
                <p class="submit" style="text-align:right; margin-top:20px;">
                    <button type="button" class="button" id="onik-youtube-modal-cancel">Cancel</button>
                    <button type="button" class="button button-primary" id="onik-youtube-modal-save">Save</button>
                </p>
            </div>
        </div>
    </div>

    <?php if (onik_images_is_advanced_mode()): ?>
        <div style="margin-top: 10px;">
            <a href="#" id="onik-youtube-debug-json-link"
                style="text-decoration: none; border-bottom: 1px dashed #0073aa;">Debug JSON</a>
            <div id="onik-youtube-debug-json-popup"
                style="display: none; position: absolute; background: #fff; border: 1px solid #ccc; padding: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); z-index: 9999; max-width: 600px; max-height: 400px; overflow: auto;">
                <pre style="margin: 0; font-family: monospace; white-space: pre-wrap;"></pre>
            </div>
        </div>
    <?php endif; ?>

    <script>
        jQuery(document).ready(function ($) {
            var $table = $('#onik_images_youtube_settings_table tbody');
            var $modal = $('#onik-youtube-settings-modal');
            var $modalForm = $('#onik-youtube-modal-form');
            var $modalTitle = $('#onik-youtube-modal-title');
            var $rowIndexInput = $('#onik-youtube-modal-row-index');

            // Move modal to body
            $('body').append($modal);

            function updateRowIndices() {
                $table.find('tr').each(function (index) {
                    $(this).find('input[type="hidden"]').each(function () {
                        var name = $(this).attr('name');
                        if (name) {
                            var newName = name.replace(/\[\d+\]/, '[' + index + ']');
                            $(this).attr('name', newName);
                        }
                    });
                });
            }

            function openModal(row) {
                $modalForm.find('input[type="text"]').val('');
                $modalForm.find('select').val('0');
                $rowIndexInput.val('');
                if (row) {
                    $modalTitle.text('Edit YouTube Setting');
                    var index = $table.find('tr').index(row);
                    $rowIndexInput.val(index);

                    $('#onik-youtube-modal-selector').val(row.find('.col-selector input').val());
                    $('#onik-youtube-modal-playlabel').val(row.find('.col-playlabel input').val());
                    $('#onik-youtube-modal-title').val(row.find('.col-title input').val());
                    $('#onik-youtube-modal-params').val(row.find('.col-params input').val());
                    $('#onik-youtube-modal-js_api').val(row.find('.col-js_api input').val());
                    $('#onik-youtube-modal-style').val(row.find('.col-style input').val());
                } else {
                    $modalTitle.text('Add YouTube Setting');
                    $rowIndexInput.val('');
                }
                $modal.show();
            }

            function closeModal() {
                $modal.hide();
            }

            $('#add-youtube-row').on('click', function () {
                openModal(null);
            });

            $table.on('click', '.edit-row', function (e) {
                e.preventDefault();
                openModal($(this).closest('tr'));
            });

            $('#onik-youtube-modal-cancel').on('click', closeModal);

            $('#onik-youtube-modal-save').on('click', function () {
                var index = $rowIndexInput.val();
                var data = {
                    selector: $('#onik-youtube-modal-selector').val(),
                    playlabel: $('#onik-youtube-modal-playlabel').val(),
                    title: $('#onik-youtube-modal-title').val(),
                    params: $('#onik-youtube-modal-params').val(),
                    js_api: $('#onik-youtube-modal-js_api').val(),
                    style: $('#onik-youtube-modal-style').val()
                };

                if (!data.selector) {
                    alert('Selector is required.');
                    return;
                }

                if (index === '') {
                    if ($table.find('.no-items').length) {
                        $table.empty();
                    }
                    index = $table.find('tr').length;
                    var rowHtml = '<tr>';

                    // Selector
                    rowHtml += '<td class="col-selector"><span class="display-value">' + $('<div>').text(data.selector).html() + '</span><input type="hidden" name="onik_images_youtube_settings[' + index + '][selector]" value="' + $('<div>').text(data.selector).html() + '" /></td>';

                    // 
                    rowHtml += '<td class="col-playlabel"><span class="display-value">' + $('<div>').text(data.playlabel).html() + '</span><input type="hidden" name="onik_images_youtube_settings[' + index + '][playlabel]" value="' + $('<div>').text(data.playlabel).html() + '" /></td>';

                    // Title
                    rowHtml += '<td class="col-title"><span class="display-value">' + $('<div>').text(data.title).html() + '</span><input type="hidden" name="onik_images_youtube_settings[' + index + '][title]" value="' + $('<div>').text(data.title).html() + '" /></td>';

                    // Params
                    rowHtml += '<td class="col-params"><span class="display-value">' + $('<div>').text(data.params).html() + '</span><input type="hidden" name="onik_images_youtube_settings[' + index + '][params]" value="' + $('<div>').text(data.params).html() + '" /></td>';

                    // JS API
                    var jsApiDisplay = data.js_api == '1' ? 'Yes' : 'No';
                    rowHtml += '<td class="col-js_api"><span class="display-value">' + jsApiDisplay + '</span><input type="hidden" name="onik_images_youtube_settings[' + index + '][js_api]" value="' + data.js_api + '" /></td>';

                    // Style
                    rowHtml += '<td class="col-style"><span class="display-value">' + $('<div>').text(data.style).html() + '</span><input type="hidden" name="onik_images_youtube_settings[' + index + '][style]" value="' + $('<div>').text(data.style).html() + '" /></td>';

                    rowHtml += '<td><div style="display:flex; gap:5px;"><button type="button" class="button edit-row" title="Edit">✎</button><button type="button" class="button move-up" title="Move Up">↑</button><button type="button" class="button move-down" title="Move Down">↓</button><button type="button" class="button delete-row" title="Delete">×</button></div></td></tr>';
                    $table.append(rowHtml);
                } else {
                    var $row = $table.find('tr').eq(index);

                    $row.find('.col-selector .display-value').text(data.selector);
                    $row.find('.col-selector input').val(data.selector);

                    $row.find('.col-playlabel .display-value').text(data.playlabel);
                    $row.find('.col-playlabel input').val(data.playlabel);

                    $row.find('.col-title .display-value').text(data.title);
                    $row.find('.col-title input').val(data.title);

                    $row.find('.col-params .display-value').text(data.params);
                    $row.find('.col-params input').val(data.params);

                    $row.find('.col-js_api .display-value').text(data.js_api == '1' ? 'Yes' : 'No');
                    $row.find('.col-js_api input').val(data.js_api);

                    $row.find('.col-style .display-value').text(data.style);
                    $row.find('.col-style input').val(data.style);
                }

                closeModal();
            });

            $table.on('click', '.delete-row', function () {
                $(this).closest('tr').remove();
                if ($table.find('tr').length === 0) {
                    $table.append('<tr class="no-items"><td colspan="7">No settings found.</td></tr>');
                } else {
                    updateRowIndices();
                }
            });

            $table.on('click', '.move-up', function () {
                var $row = $(this).closest('tr');
                if ($row.prev().length) {
                    $row.insertBefore($row.prev());
                    updateRowIndices();
                }
            });

            $table.on('click', '.move-down', function () {
                var $row = $(this).closest('tr');
                if ($row.next().length) {
                    $row.insertAfter($row.next());
                    updateRowIndices();
                }
            });

            // Debug JSON Popup Logic
            var $debugLink = $('#onik-youtube-debug-json-link');
            var $popup = $('#onik-youtube-debug-json-popup');
            var $pre = $popup.find('pre');

            $debugLink.on('mouseenter', function (e) {
                var data = {};

                $table.find('tr').each(function () {
                    var $row = $(this);
                    if ($row.hasClass('no-items')) return;

                    var selector = $row.find('input[name*="[selector]"]').val();
                    if (!selector) return;

                    var config = {};

                    var playlabel = $row.find('input[name*="[playlabel]"]').val();
                    if (playlabel) config.playlabel = playlabel;

                    var title = $row.find('input[name*="[title]"]').val();
                    if (title) config.title = title;

                    var params = $row.find('input[name*="[params]"]').val();
                    if (params) config.params = params;

                    var js_api = $row.find('input[name*="[js_api]"]').val();
                    if (js_api == '1') config.js_api = true;

                    var style = $row.find('input[name*="[style]"]').val();
                    if (style) config.style = style;

                    data[selector] = config;
                });

                $pre.text(JSON.stringify(data, null, 4));

                var offset = $debugLink.offset();
                $popup.css({
                    top: offset.top + 20,
                    left: offset.left
                }).show();
            });

            $debugLink.on('mouseleave', function () {
                $popup.hide();
            });
        });
    </script>
    <?php
}

function onik_images_settings_regex_replace_callback()
{
    onik_images_addTextareaOption('onik_images_regex_replace');
    echo '<p>Enter regex replace configurations in JSON format. Each configuration should have a "targetKey" field (e.g., "rentalimage_imageloc") and optionally quality, format, width, and urlFilter. The plugin will automatically build the appropriate regex patterns to find and replace image URLs in JSON-like structures.</p>';
}

function onik_images_settings_preloads_callback()
{
    $setting = get_option('onik_images_preloads');
    $converter = new \OnikImages\SettingsConverter();
    $tableData = $converter->preloadsJsonToTable($setting ?: '[]');

    ?>
    <style>
        #onik_images_preloads_table {
            width: 100%;
            table-layout: fixed;
        }

        #onik_images_preloads_table th,
        #onik_images_preloads_table td {
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .col-rel {
            width: 10%;
        }

        .col-fetchpriority {
            width: 10%;
        }

        .col-as {
            width: 10%;
        }

        .col-href {
            width: 30%;
        }

        .col-type {
            width: 15%;
        }

        .col-urlFilter {
            width: 15%;
        }

        .col-actions {
            width: 10%;
        }
    </style>
    <div class="wrap">
        <table class="widefat fixed" id="onik_images_preloads_table">
            <thead>
                <tr>
                    <th class="col-rel">Rel</th>
                    <th class="col-fetchpriority">Fetch Priority</th>
                    <th class="col-as">As</th>
                    <th class="col-href">Href</th>
                    <th class="col-type">Type</th>
                    <th class="col-urlFilter">URL Filter</th>
                    <th class="col-actions">Actions</th>
                </tr>
            </thead>
            <tbody id="onik_images_preloads_tbody">
                <?php if (empty($tableData)): ?>
                    <tr class="no-items">
                        <td colspan="7">No preloads found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($tableData as $index => $row): ?>
                        <tr>
                            <td class="col-rel">
                                <span class="display-value"><?php echo esc_html($row['rel']); ?></span>
                                <input type="hidden" name="onik_images_preloads[<?php echo $index; ?>][rel]"
                                    value="<?php echo esc_attr($row['rel']); ?>" />
                            </td>
                            <td class="col-fetchpriority">
                                <span class="display-value"><?php echo esc_html($row['fetchpriority']); ?></span>
                                <input type="hidden" name="onik_images_preloads[<?php echo $index; ?>][fetchpriority]"
                                    value="<?php echo esc_attr($row['fetchpriority']); ?>" />
                            </td>
                            <td class="col-as">
                                <span class="display-value"><?php echo esc_html($row['as']); ?></span>
                                <input type="hidden" name="onik_images_preloads[<?php echo $index; ?>][as]"
                                    value="<?php echo esc_attr($row['as']); ?>" />
                            </td>
                            <td class="col-href">
                                <span class="display-value"><?php echo esc_html($row['href']); ?></span>
                                <input type="hidden" name="onik_images_preloads[<?php echo $index; ?>][href]"
                                    value="<?php echo esc_attr($row['href']); ?>" />
                            </td>
                            <td class="col-type">
                                <span class="display-value"><?php echo esc_html($row['type']); ?></span>
                                <input type="hidden" name="onik_images_preloads[<?php echo $index; ?>][type]"
                                    value="<?php echo esc_attr($row['type']); ?>" />
                            </td>
                            <td class="col-urlFilter">
                                <span class="display-value"><?php echo esc_html($row['urlFilter']); ?></span>
                                <input type="hidden" name="onik_images_preloads[<?php echo $index; ?>][urlFilter]"
                                    value="<?php echo esc_attr($row['urlFilter']); ?>" />
                            </td>
                            <td>
                                <div style="display:flex; gap:5px;">
                                    <button type="button" class="button edit-row" title="Edit">✎</button>
                                    <button type="button" class="button move-up" title="Move Up">↑</button>
                                    <button type="button" class="button move-down" title="Move Down">↓</button>
                                    <button type="button" class="button delete-row" title="Delete">×</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <p>
            <button type="button" class="button" id="add-preload-row">Add Row</button>
        </p>
    </div>

    <div id="onik-preloads-modal"
        style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:100000;">
        <div
            style="background:#fff; width:800px; margin:100px auto; padding:20px; border-radius:5px; box-shadow:0 0 10px rgba(0,0,0,0.3); max-height: 80vh; overflow-y: auto;">
            <h2 id="onik-preload-modal-title" style="margin-top:0;">Edit Preload</h2>
            <div id="onik-preload-modal-form">
                <input type="hidden" id="onik-preload-modal-row-index" value="">
                <table class="form-table">
                    <tr>
                        <th><label for="onik-preload-modal-rel">Rel</label></th>
                        <td>
                            <select id="onik-preload-modal-rel">
                                <option value="preload">preload</option>
                                <option value="prefetch">prefetch</option>
                                <option value="dns-prefetch">dns-prefetch</option>
                                <option value="preconnect">preconnect</option>
                            </select>
                            <p class="description">Link relationship type (default: "preload").</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="onik-preload-modal-fetchpriority">Fetch Priority</label></th>
                        <td>
                            <select id="onik-preload-modal-fetchpriority">
                                <option value="">Default</option>
                                <option value="high">High</option>
                                <option value="low">Low</option>
                            </select>
                            <p class="description">Fetch priority for the resource ("high" or "low").</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="onik-preload-modal-as">As</label></th>
                        <td>
                            <select id="onik-preload-modal-as">
                                <option value="">Select type...</option>
                                <option value="image">image</option>
                                <option value="script">script</option>
                                <option value="style">style</option>
                                <option value="font">font</option>
                                <option value="fetch">fetch</option>
                                <option value="document">document</option>
                                <option value="video">video</option>
                                <option value="audio">audio</option>
                            </select>
                            <p class="description">Type of resource being loaded (required for preload).</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="onik-preload-modal-href">Href</label></th>
                        <td>
                            <input type="text" id="onik-preload-modal-href" class="regular-text" style="width:100%;"
                                placeholder="https://example.com/resource">
                            <p class="description">URL of the resource to preload (required).</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="onik-preload-modal-type">Type</label></th>
                        <td>
                            <input type="text" id="onik-preload-modal-type" class="regular-text" style="width:100%;"
                                placeholder="image/jpeg">
                            <p class="description">MIME type of the resource (e.g., "image/jpeg", "text/css").</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="onik-preload-modal-urlFilter">URL Filter</label></th>
                        <td>
                            <input type="text" id="onik-preload-modal-urlFilter" class="regular-text" style="width:100%;"
                                placeholder="#/blog/.*#">
                            <p class="description">Optional regex pattern to only inject preloads on specific pages (e.g.,
                                "#/blog/.*#").</p>
                        </td>
                    </tr>
                </table>
                <p class="submit" style="text-align:right; margin-top:20px;">
                    <button type="button" class="button" id="onik-preload-modal-cancel">Cancel</button>
                    <button type="button" class="button button-primary" id="onik-preload-modal-save">Save</button>
                </p>
            </div>
        </div>
    </div>

    <?php if (onik_images_is_advanced_mode()): ?>
        <div style="margin-top: 10px;">
            <a href="#" id="onik-preload-debug-json-link"
                style="text-decoration: none; border-bottom: 1px dashed #0073aa;">Debug JSON</a>
            <div id="onik-preload-debug-json-popup"
                style="display: none; position: absolute; background: #fff; border: 1px solid #ccc; padding: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); z-index: 9999; max-width: 600px; max-height: 400px; overflow: auto;">
                <pre style="margin: 0; font-family: monospace; white-space: pre-wrap;"></pre>
            </div>
        </div>
    <?php endif; ?>

    <script>
        jQuery(document).ready(function ($) {
            var $table = $('#onik_images_preloads_table tbody');
            var $modal = $('#onik-preloads-modal');
            var $modalForm = $('#onik-preload-modal-form');
            var $modalTitle = $('#onik-preload-modal-title');
            var $rowIndexInput = $('#onik-preload-modal-row-index');

            $('body').append($modal);

            function updateRowIndices() {
                $table.find('tr').each(function (index) {
                    $(this).find('input[type="hidden"]').each(function () {
                        var name = $(this).attr('name');
                        if (name) {
                            var newName = name.replace(/\[\d+\]/, '[' + index + ']');
                            $(this).attr('name', newName);
                        }
                    });
                });
            }

            function openModal(row) {
                $modalForm.find('input[type="text"]').val('');
                $modalForm.find('select').each(function () {
                    if ($(this).attr('id') === 'onik-preload-modal-rel') {
                        $(this).val('preload');
                    } else {
                        $(this).val('');
                    }
                });
                $rowIndexInput.val('');
                if (row) {
                    $modalTitle.text('Edit Preload');
                    var index = $table.find('tr').index(row);
                    $rowIndexInput.val(index);

                    $('#onik-preload-modal-rel').val(row.find('.col-rel input').val());
                    $('#onik-preload-modal-fetchpriority').val(row.find('.col-fetchpriority input').val());
                    $('#onik-preload-modal-as').val(row.find('.col-as input').val());
                    $('#onik-preload-modal-href').val(row.find('.col-href input').val());
                    $('#onik-preload-modal-type').val(row.find('.col-type input').val());
                    $('#onik-preload-modal-urlFilter').val(row.find('.col-urlFilter input').val());
                } else {
                    $modalTitle.text('Add Preload');
                    $rowIndexInput.val('');
                }
                $modal.show();
            }

            function closeModal() {
                $modal.hide();
            }

            $('#add-preload-row').on('click', function () {
                openModal(null);
            });

            $table.on('click', '.edit-row', function (e) {
                e.preventDefault();
                openModal($(this).closest('tr'));
            });

            $('#onik-preload-modal-cancel').on('click', closeModal);

            $('#onik-preload-modal-save').on('click', function () {
                var index = $rowIndexInput.val();
                var data = {
                    rel: $('#onik-preload-modal-rel').val(),
                    fetchpriority: $('#onik-preload-modal-fetchpriority').val(),
                    as: $('#onik-preload-modal-as').val(),
                    href: $('#onik-preload-modal-href').val(),
                    type: $('#onik-preload-modal-type').val(),
                    urlFilter: $('#onik-preload-modal-urlFilter').val()
                };

                if (!data.href) {
                    alert('Href is required.');
                    return;
                }

                if (index === '') {
                    if ($table.find('.no-items').length) {
                        $table.empty();
                    }
                    index = $table.find('tr').length;
                    var rowHtml = '<tr>';

                    rowHtml += '<td class="col-rel"><span class="display-value">' + $('<div>').text(data.rel).html() + '</span><input type="hidden" name="onik_images_preloads[' + index + '][rel]" value="' + $('<div>').text(data.rel).html() + '" /></td>';
                    rowHtml += '<td class="col-fetchpriority"><span class="display-value">' + $('<div>').text(data.fetchpriority).html() + '</span><input type="hidden" name="onik_images_preloads[' + index + '][fetchpriority]" value="' + $('<div>').text(data.fetchpriority).html() + '" /></td>';
                    rowHtml += '<td class="col-as"><span class="display-value">' + $('<div>').text(data.as).html() + '</span><input type="hidden" name="onik_images_preloads[' + index + '][as]" value="' + $('<div>').text(data.as).html() + '" /></td>';
                    rowHtml += '<td class="col-href"><span class="display-value">' + $('<div>').text(data.href).html() + '</span><input type="hidden" name="onik_images_preloads[' + index + '][href]" value="' + $('<div>').text(data.href).html() + '" /></td>';
                    rowHtml += '<td class="col-type"><span class="display-value">' + $('<div>').text(data.type).html() + '</span><input type="hidden" name="onik_images_preloads[' + index + '][type]" value="' + $('<div>').text(data.type).html() + '" /></td>';
                    rowHtml += '<td class="col-urlFilter"><span class="display-value">' + $('<div>').text(data.urlFilter).html() + '</span><input type="hidden" name="onik_images_preloads[' + index + '][urlFilter]" value="' + $('<div>').text(data.urlFilter).html() + '" /></td>';

                    rowHtml += '<td><div style="display:flex; gap:5px;"><button type="button" class="button edit-row" title="Edit">✎</button><button type="button" class="button move-up" title="Move Up">↑</button><button type="button" class="button move-down" title="Move Down">↓</button><button type="button" class="button delete-row" title="Delete">×</button></div></td></tr>';
                    $table.append(rowHtml);
                } else {
                    var $row = $table.find('tr').eq(index);

                    $row.find('.col-rel .display-value').text(data.rel);
                    $row.find('.col-rel input').val(data.rel);

                    $row.find('.col-fetchpriority .display-value').text(data.fetchpriority);
                    $row.find('.col-fetchpriority input').val(data.fetchpriority);

                    $row.find('.col-as .display-value').text(data.as);
                    $row.find('.col-as input').val(data.as);

                    $row.find('.col-href .display-value').text(data.href);
                    $row.find('.col-href input').val(data.href);

                    $row.find('.col-type .display-value').text(data.type);
                    $row.find('.col-type input').val(data.type);

                    $row.find('.col-urlFilter .display-value').text(data.urlFilter);
                    $row.find('.col-urlFilter input').val(data.urlFilter);
                }

                closeModal();
            });

            $table.on('click', '.delete-row', function () {
                $(this).closest('tr').remove();
                if ($table.find('tr').length === 0) {
                    $table.append('<tr class="no-items"><td colspan="7">No preloads found.</td></tr>');
                } else {
                    updateRowIndices();
                }
            });

            $table.on('click', '.move-up', function () {
                var $row = $(this).closest('tr');
                if ($row.prev().length) {
                    $row.insertBefore($row.prev());
                    updateRowIndices();
                }
            });

            $table.on('click', '.move-down', function () {
                var $row = $(this).closest('tr');
                if ($row.next().length) {
                    $row.insertAfter($row.next());
                    updateRowIndices();
                }
            });

            // Debug JSON Popup Logic
            var $debugLink = $('#onik-preload-debug-json-link');
            var $popup = $('#onik-preload-debug-json-popup');
            var $pre = $popup.find('pre');

            $debugLink.on('mouseenter', function (e) {
                var data = [];

                $table.find('tr').each(function () {
                    var $row = $(this);
                    if ($row.hasClass('no-items')) return;

                    var config = {};

                    var rel = $row.find('input[name*="[rel]"]').val();
                    if (rel && rel !== 'preload') config.rel = rel;

                    var fetchpriority = $row.find('input[name*="[fetchpriority]"]').val();
                    if (fetchpriority) config.fetchpriority = fetchpriority;

                    var as = $row.find('input[name*="[as]"]').val();
                    if (as) config.as = as;

                    var href = $row.find('input[name*="[href]"]').val();
                    if (href) config.href = href;

                    var type = $row.find('input[name*="[type]"]').val();
                    if (type) config.type = type;

                    var urlFilter = $row.find('input[name*="[urlFilter]"]').val();
                    if (urlFilter) config.urlFilter = urlFilter;

                    if (config.href) {
                        data.push(config);
                    }
                });

                $pre.text(JSON.stringify(data, null, 4));

                var offset = $debugLink.offset();
                $popup.css({
                    top: offset.top + 20,
                    left: offset.left
                }).show();
            });

            $debugLink.on('mouseleave', function () {
                $popup.hide();
            });
        });
    </script>
    <?php
}

function onik_images_settings_script_block_callback()
{
    onik_images_addTextareaOption('onik_images_script_block');
    echo '<p>Enter script block configurations in JSON format. Each script block should have a "selector" field (CSS selector) and optionally src (string), type (string), async (boolean), defer (boolean), and urlFilter (string). The plugin will automatically include the script on pages where the URL path matches the selector.</p>';
}


function onik_images_addTextOption($name)
{
    $setting = get_option($name);
    ?>
    <input type="text" name="<?php echo esc_attr($name); ?>"
        value="<?php echo isset($setting) ? esc_attr($setting) : ''; ?>" class="regular-text">
    <?php
}
function onik_images_addTextareaOption($name)
{
    $setting = get_option($name);

    // Check if there's a submitted value (for validation errors)
    $submitted_value = '';
    if (isset($_POST[$name])) {
        $submitted_value = $_POST[$name];
    }

    // Use submitted value if available (for validation errors), otherwise use saved setting
    $display_value = !empty($submitted_value) ? $submitted_value : (isset($setting) ? $setting : '');
    ?>
    <textarea name="<?php echo esc_attr($name); ?>" class="regular-text" rows="10"
        style="min-height: 500px; width: 100%;"><?php echo esc_textarea($display_value); ?></textarea>
    <?php
}
function onik_images_addCheckboxOption($name)
{
    $setting = get_option($name);
    ?>
    <input type="checkbox" name="<?php echo esc_attr($name); ?>" value="1" <?php checked(1, $setting); ?>>
    <?php
}


function onik_images_register_ob_start()
{
    // Only start output buffering if not running tests
    if (defined('ONIK_IMAGES_TESTS')) {
        return;
    }
    if (is_admin() || (defined('DOING_AJAX') && DOING_AJAX)) {
        return;
    }

    if (wp_doing_ajax() || wp_is_json_request()) {
        return;
    }
    if (onik_images_get_current_request_path() && strpos(onik_images_get_current_request_path(), '/wp-json/') !== false) {
        return;
    }
    // if is activated, return
    if (get_option('onik_lens_activated') !== '1') {
        return;
    }
    ob_start('alter_html');
}

//onik_images_register_ob_start();

//Doing this within the template_redirect action hook is better than using ob_start() in the plugin file
//because it works better with caching plugins
add_action('template_redirect', function () {
    onik_images_register_ob_start();
});

/**
 * Original approach: Use DOM manipulation for all modifications
 * This approach may modify HTML structure due to libxml's HTML correction
 */
function alter_html($html, $current_path_override = null)
{
    return alter_html_hybrid($html, $current_path_override);
}

/**
 * Inject preload link tags into the HTML head
 * 
 * @param DOMDocument $preloadDom The DOM document to modify
 * @param array $preloads Array of preload configurations
 * @param string|null $current_path_override Optional override for current path (for testing)
 */
function buildPreloadLinkTags($dom, $preloads, $current_path_override = null)
{
    // Get current request path for URL filtering
    $current_path = $current_path_override !== null ? $current_path_override : onik_images_get_current_request_path();
    $appliedPreloads = 0;

    $preloadDom = new DOMDocument();

    // Create and inject preload link tags
    foreach ($preloads as $preload) {

        if (isset($preload['urlFilter']) && !empty($preload['urlFilter'])) {
            if (!preg_match($preload['urlFilter'], $current_path)) {
                continue;
            }
        }
        // Skip if the original dom has no head element
        if ($dom->getElementsByTagName('head')->length === 0) {
            continue;
        }

        //Ignore if the current path is a .xml or .json file
        if (strpos($current_path, '.xml') !== false || strpos($current_path, '.json') !== false) {
            continue;
        }

        $linkElement = $preloadDom->createElement('link');
        if (isset($preload['rel']) && !empty($preload['rel'])) {
            $linkElement->setAttribute('rel', $preload['rel']);
        } else {
            $linkElement->setAttribute('rel', 'preload');
        }

        if (isset($preload['fetchpriority']) && !empty($preload['fetchpriority'])) {
            $linkElement->setAttribute('fetchpriority', strtolower($preload['fetchpriority']));
        }
        if (isset($preload['as']) && !empty($preload['as'])) {
            $linkElement->setAttribute('as', $preload['as']);
        }
        if (isset($preload['href']) && !empty($preload['href'])) {
            $linkElement->setAttribute('href', $preload['href']);
        }

        if (isset($preload['type']) && !empty($preload['type'])) {
            $linkElement->setAttribute('type', $preload['type']);
        }

        // Insert the preload link at the beginning of the head
        $preloadDom->appendChild($linkElement);
        $appliedPreloads++;
    }
    return $preloadDom->saveHTML();
}

/**
 * Collect scripts to block based on URL patterns
 * 
 * @param DOMDocument $dom The DOM document to search
 * @param array $scriptBlocks Array of script block configurations
 * @param string|null $current_path_override Optional override for current path (for testing)
 * @return array Array of modifications to apply
 */
function collectScriptsToBlock($dom, $scriptBlocks, $current_path_override = null)
{
    // Get current request path for URL filtering
    $current_path = $current_path_override !== null ? $current_path_override : onik_images_get_current_request_path();
    $modifications = [];

    // Find all script tags in the DOM
    $scriptElements = $dom->getElementsByTagName('script');

    foreach ($scriptElements as $scriptElement) {
        $src = $scriptElement->getAttribute('src');

        // Skip scripts without src attribute
        if (empty($src)) {
            continue;
        }

        // Check each script block configuration
        foreach ($scriptBlocks as $config) {
            // Check URL filter if present
            if (isset($config['urlFilter']) && !empty($config['urlFilter'])) {
                if (!preg_match($config['urlFilter'], $current_path)) {
                    continue;
                }
            }

            // Check if script URL matches the pattern
            $urlPattern = $config['urlPattern'];

            // Check if pattern is a regex (starts with / or #) or a simple string
            if (preg_match('/^[\/#].*[\/#]$/', $urlPattern)) {
                // Treat as regex pattern
                if (preg_match($urlPattern, $src)) {
                    // Create modification to remove this script
                    $scriptHtml = $dom->saveHTML($scriptElement);
                    // Reconstruct the script tag with all attributes preserved
                    $originalScriptHtml = '<script';
                    foreach ($scriptElement->attributes as $attr) {
                        $originalScriptHtml .= ' ' . $attr->name;
                        // Handle boolean attributes (like defer, async, etc.)
                        if ($attr->value === '' || $attr->value === $attr->name) {
                            // Boolean attribute - just add the name (no value)
                        } else {
                            $originalScriptHtml .= '="' . $attr->value . '"';
                        }
                    }
                    $originalScriptHtml .= '></script>';

                    // Also try a version without boolean attributes for better matching
                    $simpleScriptHtml = '<script src="' . $src . '"></script>';

                    $modifications[] = [
                        'search' => $originalScriptHtml,
                        'replace' => '', // Remove the script entirely
                        'pattern' => null,
                        'selector' => 'script[src*="' . $src . '"]',
                        'src' => $src,
                        'originalHtml' => $scriptHtml,
                        'simpleHtml' => $simpleScriptHtml // Keep simple version for fallback
                    ];
                    break; // Only match one configuration per script
                }
            } else {
                // Treat as simple string pattern
                if (strpos($src, $urlPattern) !== false) {
                    // Create modification to remove this script
                    // Use the manually constructed HTML to avoid encoding issues
                    $scriptHtml = $dom->saveHTML($scriptElement);
                    // Reconstruct the script tag with all attributes preserved
                    $originalScriptHtml = '<script';
                    foreach ($scriptElement->attributes as $attr) {
                        $originalScriptHtml .= ' ' . $attr->name;
                        // Handle boolean attributes (like defer, async, etc.)
                        if ($attr->value === '' || $attr->value === $attr->name) {
                            // Boolean attribute - just add the name (no value)
                        } else {
                            $originalScriptHtml .= '="' . $attr->value . '"';
                        }
                    }
                    $originalScriptHtml .= '></script>';

                    // Also try a version without boolean attributes for better matching
                    $simpleScriptHtml = '<script src="' . $src . '"></script>';

                    // Also try a version with just the essential attributes
                    $essentialScriptHtml = '<script src="' . $src . '"';
                    if ($scriptElement->hasAttribute('defer')) {
                        $essentialScriptHtml .= ' defer';
                    }
                    if ($scriptElement->hasAttribute('async')) {
                        $essentialScriptHtml .= ' async';
                    }
                    $essentialScriptHtml .= '></script>';

                    $modifications[] = [
                        'search' => $originalScriptHtml, // Use manually constructed HTML as primary search
                        'replace' => '', // Remove the script entirely
                        'pattern' => null,
                        'selector' => 'script[src*="' . $src . '"]',
                        'src' => $src,
                        'originalHtml' => $scriptHtml, // Keep DOM-generated HTML as fallback
                        'simpleHtml' => $simpleScriptHtml, // Keep simple version for fallback
                        'essentialHtml' => $essentialScriptHtml // Keep essential version for fallback
                    ];
                    break; // Only match one configuration per script
                }
            }
        }
    }

    return $modifications;
}

/**
 * Hybrid approach: Use DOM for element discovery, string replacement for modifications
 * This prevents libxml from modifying unrelated HTML content
 */
function alter_html_hybrid($html, $current_path_override = null)
{

    // Capture a timestamp
    $startTime = microtime(true);


    if (empty($html)) {
        return $html;
    }

    if (strlen(string: $html) < 10) {
        return $html;
    }

    // If there is no <html> tag present, just return the $html (likely not HTML)
    if (stripos($html, '<html') === false) {
        return $html;
    }

    $image_converter_url = get_option('onik_images_image_converter_url');
    if (empty($image_converter_url) || is_null($image_converter_url)) {
        return $html;
    }

    // Validate URL format
    $trimmed_url = trim($image_converter_url);
    if (!filter_var($trimmed_url, FILTER_VALIDATE_URL)) {
        return $html;
    }

    // Ensure URL has trailing slash
    if (substr($trimmed_url, -1) !== '/') {
        return $html;
    }

    // Check if the plugin is enabled
    $enabled = get_option('onik_images_enabled');
    if (!$enabled) {
        return $html;
    }

    $activation = new \OnikImages\LensActivation();
    if (!$activation->isActivated()) {
        return $html;
    }

    $tenant = get_option('onik_images_tenant');
    $site = get_option('onik_images_site');
    $appendLocation = $trimmed_url . $tenant . '/' . $site . '/';
    $debug = get_option('onik_images_debug');
    $selectorWidthMappingString = get_option('onik_images_image_settings');
    $selectorWidthMapping = json_decode($selectorWidthMappingString, true);
    // Validate selectorWidthMapping is an array
    if (!is_array($selectorWidthMapping)) {
        $selectorWidthMapping = [];
    }

    // Get regex-based configurations from the new dedicated setting
    $regexConfigs = [];
    $regexReplaceString = get_option('onik_images_regex_replace');
    if (!empty($regexReplaceString)) {
        $regexConfigs = json_decode($regexReplaceString, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('ONIK Images: Invalid JSON in regex_replace setting: ' . json_last_error_msg());
            $regexConfigs = [];
        }
        // Validate regexConfigs is an array
        if (!is_array($regexConfigs)) {
            $regexConfigs = [];
        }
    }



    $preloadsString = get_option('onik_images_preloads');
    $preloads = json_decode($preloadsString, true);
    // Validate preloads is an array
    if (!is_array($preloads)) {
        $preloads = [];
    }

    // Get script block configurations
    $scriptBlocksString = get_option('onik_images_script_block');
    $scriptBlocks = [];
    if (!empty($scriptBlocksString)) {
        $scriptBlocks = json_decode($scriptBlocksString, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('ONIK Images: Invalid JSON in script_block setting: ' . json_last_error_msg());
            $scriptBlocks = [];
        }
        // Validate scriptBlocks is an array
        if (!is_array($scriptBlocks)) {
            $scriptBlocks = [];
        }
    }

    if (empty($selectorWidthMapping) && empty($preloads) && empty($regexConfigs) && empty($scriptBlocks)) {
        return $html;
    }

    // Track processed images per selector for lazy loading logic
    $processedImageCounts = [];

    // Collect all modifications to apply via string replacement
    $modifications = [];

    // Parse HTML for element discovery only
    $libxml_previous_state = libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML($html, LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED | LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_NONET);
    $domErrors = libxml_get_errors();
    libxml_clear_errors();
    libxml_use_internal_errors($libxml_previous_state);

    // Track processed elements to ensure only the first matching selector applies
    $processedElements = new SplObjectStorage();

    // Process image configurations
    if (!empty($selectorWidthMapping)) {
        foreach ($selectorWidthMapping as $selector => $config) {
            $elements = onik_images_query_css($dom, $selector);

            if ($elements !== false) {
                // Initialize counter for this selector if not exists
                if (!isset($processedImageCounts[$selector])) {
                    $processedImageCounts[$selector] = 0;
                }

                foreach ($elements as $element) {
                    // Skip if this element has already been processed by a previous selector
                    if ($processedElements->contains($element)) {
                        continue;
                    }

                    // Skip if this element has the onik-ignore class
                    $elementClasses = preg_split('/\s+/', trim($element->getAttribute('class')));
                    if (in_array('onik-ignore', $elementClasses)) {
                        continue;
                    }

                    if ($element->tagName == 'img') {

                        $newModifications = collectImgModifications($element, $appendLocation, $selector, $config, $processedImageCounts[$selector], $html);
                        //dedupe the new modifications

                        $modifications = array_merge($modifications, $newModifications);
                        $processedImageCounts[$selector]++;
                        $processedElements->attach($element);
                    } else if ($element->tagName == 'div' || $element->tagName == 'span') {
                        $modifications = array_merge(
                            $modifications,
                            collectDivModifications($element, $appendLocation, $selector, $config, $html)
                        );
                        $processedElements->attach($element);
                    } else {
                        //TODO: Handle other elements
                        error_log('ONIK Images: Unsupported element type: ' . $element->tagName);
                    }
                }
            }
        }
    }

    // Collect inline style block modifications
    if (!empty($selectorWidthMapping)) {
        $modifications = array_merge($modifications, collectInlineStyleBlockModifications($dom, $selectorWidthMapping, $appendLocation, $html));
    }



    // Collect regex-based modifications that operate on the entire HTML
    if (!empty($regexConfigs)) {
        $modifications = array_merge($modifications, collectRegexModifications($html, $appendLocation, $regexConfigs));
    }

    // Collect script block modifications
    if (!empty($scriptBlocks)) {
        $modifications = array_merge($modifications, collectScriptsToBlock($dom, $scriptBlocks, $current_path_override));
    }

    // Collect YouTube embed modifications if enabled
    $youtube_enabled = get_option('onik_images_youtube_enabled');
    if ($youtube_enabled) {
        $modifications = array_merge($modifications, collectYouTubeModifications($dom, $html));
    }

    $modificationsDebug = "";
    // Apply all modifications via string replacement
    $modifiedHtml = $html;
    $modIndex = 0;
    $modifications = array_unique($modifications, SORT_REGULAR);

    if (!empty($preloads) && is_array($preloads) && count($preloads) > 0) {
        $preloadsString = buildPreloadLinkTags($dom, $preloads, $current_path_override);
        if (!empty($preloadsString)) {
            $modifiedHtml = str_replace('</head>', $preloadsString . '</head>', $modifiedHtml);
        }
    }


    foreach ($modifications as $modification) {
        $modIndex++;


        $modifiedCount = 0;
        $modificationsDebug .= $modIndex . ' Attempting String Replace\n';
        $modifiedHtml = str_replace($modification['search'], $modification['replace'], $modifiedHtml, $modifiedCount);

        // If the first search failed and this is a script blocking modification, try the original HTML version
        if ($modifiedCount == 0 && isset($modification['originalHtml'])) {
            $modifiedHtml = str_replace($modification['originalHtml'], $modification['replace'], $modifiedHtml, $modifiedCount);
        }

        // If still failed and this is a script blocking modification, try the simple HTML version
        if ($modifiedCount == 0 && isset($modification['simpleHtml'])) {
            $modifiedHtml = str_replace($modification['simpleHtml'], $modification['replace'], $modifiedHtml, $modifiedCount);
        }

        if ($modifiedCount == 0) {
            $modificationsDebug .= $modIndex . ' Attempting Preg Replace\n';
            $pattern = $modification['pattern'];
            if ($pattern == null) {
                $modificationsDebug .= "Non IMG Modification Search not found: \n" . $modification['search'] . " \n Selector: \n" . $modification['selector'] . " \nMatchesString: \n" . $modification['src'] . "\nbtoa \n" . bin2hex($modification['src']) . "\nbota_search \n" . bin2hex($modification['search']) . "\n\n\n\n";
            } else {
                //* Find all image tages with the same src
                $matches = [];
                $candidatesOriginalHtml = preg_match_all($pattern, $html, $matches);
                $candidatesModifiedHtml = preg_match_all($pattern, $modifiedHtml, $matches);

                $preg_replace_count = 0;
                if (count($matches[0]) > 0) {
                    $modificationsDebug .= "Attempting Preg Replace\n " . $pattern . "\n";
                    $modifiedHtml = preg_replace($pattern, $modification['replace'], $modifiedHtml, -1, $preg_replace_count);
                    $modificationsDebug .= "Preg Replace Count: " . $preg_replace_count . "\n";
                }

                $modifiedCount = $preg_replace_count;
            }
        }

        if ($modifiedCount > 0) {
            $modificationsDebug .= $modIndex . " Modified: \nSearch:\n " . $modification['search'] . " \n Replace:\n" . $modification['replace'] . " \n Selector: \n" . $modification['selector'] . "\n src: \n" . $modification['src'] . "\n\n\n\n";
        } else {
            $modificationsDebug .= $modIndex . " Not Modified: \nSearch:\n " . $modification['search'] . " \n Replace:\n" . $modification['replace'] . " \n Selector: \n" . $modification['selector'] . "\n src: \n" . $modification['src'] . "\n\n\n\n";
        }
    }


    $endTime = microtime(true);

    $alterHtmlExecutionMS = ($endTime - $startTime);
    // Add debug comment if enabled
    if ($debug) {
        $debugComment = '<!-- ONIK Images
Timestamp: ' . date("h:i:s") . '
Alter HTML Execution Time: ' . $alterHtmlExecutionMS . 's
Current Path: ' . ($current_path_override !== null ? $current_path_override : onik_images_get_current_request_path()) . '
Tenant: ' . $tenant . '
Site: ' . $site . '
Image Converter URL: ' . $trimmed_url . '
Image Settings: ' . json_encode($selectorWidthMapping) . '
Image Settings String: ' . $selectorWidthMappingString . '
Preloads: ' . json_encode($preloads) . '
Append Location: ' . $appendLocation . '
Processed Image Counts: ' . json_encode($processedImageCounts) . '
YouTube Enabled: ' . ($youtube_enabled ? 'true' : 'false') . '
Applied Preloads: ' . count($preloads) . '
Modifications Applied: ' . count($modifications) . '

Modifications Debug: ' . $modificationsDebug . '
-->';

        // Insert debug comment after <body> tag
        $headPos = stripos($modifiedHtml, '</body>');
        if ($headPos !== false) {
            $modifiedHtml = substr_replace($modifiedHtml, $debugComment . "\n", $headPos, 0);
        }
    }

    return $modifiedHtml;
}

/**
 * Collect regex-based modifications across the entire HTML document
 *
 * This scans for JSON-like key-value pairs such as
 *   "rentalimage_imageloc":"https://example.com/image.png"
 * and replaces the URL value with an ONIK image URL.
 */
function collectRegexModifications($originalHtml, $location, $regexConfigs)
{
    $modifications = [];

    // Each entry in $regexConfigs should define:
    // - targetKey: the JSON key to search for (e.g., "rentalimage_imageloc")
    // - quality: optional integer
    // - format: optional string
    // - width: optional array or integer; if array, first element is used
    // - urlFilter: optional regex pattern to match against current page URL

    foreach ($regexConfigs as $regexConfig) {
        if (!is_array($regexConfig)) {
            continue;
        }

        // Validate required fields
        if (!isset($regexConfig['targetKey']) || !is_string($regexConfig['targetKey']) || empty(trim($regexConfig['targetKey']))) {
            continue; // Skip invalid configs
        }

        // Check URL filter if present
        if (isset($regexConfig['urlFilter']) && !empty($regexConfig['urlFilter'])) {
            $currentPath = onik_images_get_current_request_path();
            if (!preg_match($regexConfig['urlFilter'], $currentPath)) {
                continue; // Skip this regex config for current page
            }
        }

        // Build the regex pattern in PHP to avoid JSON escaping issues
        $targetKey = $regexConfig['targetKey'];
        $pattern = '/"' . preg_quote($targetKey, '/') . '"\s*:\s*"([^"\\\\]+\.(?:jpg|jpeg|png|gif|webp|avif))"/i';

        // Build the replacement pattern
        $replacement = '"' . $targetKey . '": "$1"';

        $quality = isset($regexConfig['quality']) ? (int) $regexConfig['quality'] : 80;
        $format = isset($regexConfig['format']) ? (string) $regexConfig['format'] : 'auto';

        $widthParam = null;
        if (isset($regexConfig['width'])) {
            if (is_array($regexConfig['width']) && !empty($regexConfig['width'])) {
                $widthParam = (int) $regexConfig['width'][0];
            } elseif (is_numeric($regexConfig['width'])) {
                $widthParam = (int) $regexConfig['width'];
            }
        }

        if (@preg_match_all($pattern, $originalHtml, $matches, PREG_SET_ORDER) === false) {
            // Invalid pattern; skip
            continue;
        }

        foreach ($matches as $match) {
            if (!isset($match[0]) || !isset($match[1])) {
                continue;
            }
            // Full match and the captured URL
            $originalMatch = $match[0];
            $originalUrl = $match[1];

            if (!should_alter_image_based_on_src($originalUrl)) {
                continue;
            }

            $params = [
                'quality' => $quality,
            ];
            if ($format !== '') {
                $params['format'] = $format;
            }
            if ($widthParam !== null) {
                $params['width'] = $widthParam;
            }

            // Build query string in stable order
            $queryParts = [];
            foreach ($params as $k => $v) {
                $queryParts[] = $k . '=' . $v;
            }
            $queryString = implode('&', $queryParts);

            $newImageLocation = $location . rawurlencode($originalUrl) . '?' . $queryString;

            // Apply the replacement pattern with the new image location
            $newMatch = preg_replace($pattern, $replacement, $originalMatch);
            // Replace the original URL with the new image location in the result
            $newMatch = str_replace($originalUrl, $newImageLocation, $newMatch);

            $modifications[] = [
                'search' => $originalMatch,
                'replace' => $newMatch,
                'selector' => null,
                'src' => $originalUrl,
                'pattern' => null
            ];
        }
    }

    return $modifications;
}

/**
 * Extract width values from a sizes attribute
 * Parses sizes like "(max-width: 155px) 100vw, 155px" and extracts pixel values
 * 
 * @param string $sizesAttribute The sizes attribute value
 * @return array Array of unique width values in pixels
 */
function extractWidthsFromSizes($sizesAttribute)
{
    if (empty($sizesAttribute)) {
        return [];
    }

    $widths = [];

    // Match pixel values in the sizes attribute
    // This will match patterns like:
    // - (max-width: 155px)
    // - (min-width: 768px)
    // - 155px (direct pixel values)
    if (preg_match_all('/(\d+)px/', $sizesAttribute, $matches)) {
        foreach ($matches[1] as $width) {
            $widthInt = (int) $width;
            if ($widthInt > 0 && $widthInt <= 10000) {
                $widths[] = $widthInt;
                // Add double resolution width for high-resolution screens (2x)
                $doubleWidth = $widthInt * 2;
                if ($doubleWidth <= 10000) {
                    $widths[] = $doubleWidth;
                }
            }
        }
    }

    // Remove duplicates and sort
    $widths = array_unique($widths);
    sort($widths);

    return $widths;
}

/**
 * Collect modifications for an img element without modifying the DOM
 */
function collectImgModifications($imgTag, $location, $selector, $config, $processedImageCount, $originalHtml)
{
    $modifications = [];

    $src = $imgTag->getAttribute('src');
    if (!should_alter_image_based_on_src($src)) {
        return $modifications;
    }
    if ($imgTag->getAttribute('data-onik-image') == 'true') {
        return $modifications;
    }

    // Handle setWidth
    $setWidth = null;
    if (isset($config['setWidth'])) {
        $setWidth = $config['setWidth'];
    }

    // Handle setHeight
    $setHeight = null;
    if (isset($config['setHeight'])) {
        $setHeight = $config['setHeight'];
    }

    //Default is no width
    $widths = []; // Default fallback
    $widthsFromConfig = false;
    if (isset($config['widths'])) {
        $widths = $config['widths'];
        $widthsFromConfig = true;
    } else {
        $widthFound = false;

        // Check if there's an existing srcset - if so, we'll use that later
        $existingSrcset = $imgTag->getAttribute('srcset');

        // If no srcset, try to extract widths from sizes attribute
        if (!$existingSrcset) {
            $sizesAttr = $imgTag->getAttribute('sizes');
            if ($sizesAttr) {
                $extractedWidths = extractWidthsFromSizes($sizesAttr);
                if (!empty($extractedWidths)) {
                    $widths = $extractedWidths;
                    $widthFound = true;
                }
            }
        }

        if (!$widthFound) {
            // Extract width from image element as fallback
            $imgWidth = $imgTag->getAttribute('width');
            if ($imgWidth && is_numeric($imgWidth) && $imgWidth > 0 && $imgWidth <= 10000) {
                $widths = [(int) $imgWidth];
            }
        }
        // Also capture height for potential future use (stored but not used in widths array)
        $imgHeight = $imgTag->getAttribute('height');
    }

    $quality = 80;
    if (isset($config['quality'])) {
        $quality = $config['quality'];
    }
    $loading = "";
    if (isset($config['loading'])) {
        $loading = $config['loading'];
    }
    $sizes = "";
    if (isset($config['sizes'])) {
        $sizes = $config['sizes'];
    }

    // Handle lazyLoadAfter logic
    $lazyLoadAfter = 0;
    if (isset($config['lazyLoadAfter'])) {
        $lazyLoadAfter = $config['lazyLoadAfter'];
    }

    // If loading is set to 'lazy' and we haven't processed enough images yet, change to 'eager'
    if ($loading === 'lazy' && $processedImageCount < $lazyLoadAfter) {
        $loading = 'eager';
    }

    // Handle fetchpriority
    $fetchpriority = "";
    if (isset($config['fetchpriority'])) {
        $fetchpriority = $config['fetchpriority'];
    }

    // Handle decoding
    $decoding = "";
    if (isset($config['decoding'])) {
        $decoding = $config['decoding'];
    }

    // Handle format
    $format = "auto";
    if (isset($config['format'])) {
        $format = $config['format'];
    }

    // Handle srcSwap
    $srcSwap = "srcSet";
    if (isset($config['srcSwap'])) {
        $srcSwap = $config['srcSwap'];
    }

    // Handle picture option
    $picture = false;
    if (isset($config['picture'])) {
        $pictureValue = $config['picture'];
        // Properly sanitize the picture flag
        if (is_string($pictureValue)) {
            $picture = strtolower(trim($pictureValue)) === 'true';
        } elseif (is_numeric($pictureValue)) {
            $picture = (bool) $pictureValue;
        } else {
            $picture = (bool) $pictureValue;
        }
    }

    $newSrc = $location . rawurlencode($src);

    $sources = [];
    foreach ($widths as $width) {
        $sourceUrl = $newSrc . "?quality=" . $quality . "&width=" . $width;
        if ($format !== "") {
            $sourceUrl .= "&format=" . $format;
        }
        $sources[] = $sourceUrl . " " . $width . "w";
    }

    // Find the original img tag HTML in the source string

    $original = findOriginalImgHtml($imgTag, $originalHtml);
    if (!$original) {
        return [];
    }
    $originalImgHtml = $original['match'];
    $originalPattern = $original['pattern'];



    // Build the new img tag HTML
    $newImgHtml = $originalImgHtml;

    // Handle srcSwap logic
    if ($srcSwap === 'srcSet' || $srcSwap === 'srcAndSrcSet') {
        $existingSrcset = $imgTag->getAttribute('srcset');
        if ($existingSrcset && !$widthsFromConfig) {
            // Transform existing srcset
            $newSources = [];
            $parts = explode(',', $existingSrcset);
            foreach ($parts as $part) {
                $part = trim($part);
                if (preg_match('/^(\S+)\s+(\d+w)$/', $part, $matches)) {
                    $url = $matches[1];
                    $descriptor = $matches[2];
                    $width = (int) substr($descriptor, 0, -1);

                    $sourceUrl = $location . rawurlencode($url) . "?quality=" . $quality . "&width=" . $width;
                    if ($format !== "") {
                        $sourceUrl .= "&format=" . $format;
                    }
                    $newSources[] = $sourceUrl . " " . $descriptor;
                }
            }
            if (!empty($newSources)) {
                $newImgHtml = str_replace('srcset="' . $existingSrcset . '"', 'srcset="' . implode(', ', $newSources) . '"', $newImgHtml);
                // If we have sizes, ensure they are preserved or updated if needed. 
                // For now, we assume existing sizes are fine or handled by config['sizes'] if set.
                if ($sizes !== "") {
                    if (strpos($newImgHtml, 'sizes=') !== false) {
                        $newImgHtml = preg_replace('/sizes="[^"]*"/', 'sizes="' . $sizes . '"', $newImgHtml);
                    } else {
                        $newImgHtml = str_replace('<img', '<img sizes="' . $sizes . '"', $newImgHtml);
                    }
                }
            }
        } else {
            $newSrcsetVal = implode(', ', $sources);
            if ($existingSrcset) {
                $newImgHtml = str_replace('srcset="' . $existingSrcset . '"', 'srcset="' . $newSrcsetVal . '"', $newImgHtml);
                if ($sizes !== "") {
                    if (strpos($newImgHtml, 'sizes=') !== false) {
                        $newImgHtml = preg_replace('/sizes="[^"]*"/', 'sizes="' . $sizes . '"', $newImgHtml);
                    } else {
                        $newImgHtml = str_replace('<img', '<img sizes="' . $sizes . '"', $newImgHtml);
                    }
                }
            } else {
                // When adding a new srcset, preserve existing sizes or use config sizes
                $existingSizes = $imgTag->getAttribute('sizes');
                $sizesToUse = ($sizes !== "") ? $sizes : $existingSizes;

                if ($sizesToUse !== "" && $sizesToUse !== null) {
                    $newImgHtml = str_replace('<img', '<img srcset="' . $newSrcsetVal . '" sizes="' . $sizesToUse . '"', $newImgHtml);
                } else {
                    $newImgHtml = str_replace('<img', '<img srcset="' . $newSrcsetVal . '"', $newImgHtml);
                }
            }
        }
    }

    if ($srcSwap === 'src') {
        // Remove existing srcset if present
        $newImgHtml = preg_replace('/\s+srcset="[^"]*"/', '', $newImgHtml);
        // Set src to the first width (smallest) or without width if no widths provided
        if (!empty($widths)) {
            $firstSourceUrl = $newSrc . "?quality=" . $quality . "&width=" . $widths[0];
        } else {
            $firstSourceUrl = $newSrc . "?quality=" . $quality;
        }
        if ($format !== "") {
            $firstSourceUrl .= "&format=" . $format;
        }
        $newImgHtml = str_replace('src="' . $src . '"', 'src="' . $firstSourceUrl . '"', $newImgHtml);
    } elseif ($srcSwap === 'srcAndSrcSet') {
        // Set src to the first width (smallest) in addition to srcset or without width if no widths provided
        if (!empty($widths)) {
            $firstSourceUrl = $newSrc . "?quality=" . $quality . "&width=" . $widths[0];
        } else {
            $firstSourceUrl = $newSrc . "?quality=" . $quality;
        }
        if ($format !== "") {
            $firstSourceUrl .= "&format=" . $format;
        }
        $newImgHtml = str_replace('src="' . $src . '"', 'src="' . $firstSourceUrl . '"', $newImgHtml);
    }

    // Handle picture option - create picture element with source tags
    if ($picture) {
        // Create source tags for each width
        $sourceTags = '';
        foreach ($widths as $width) {
            $sourceUrl = $newSrc . "?quality=" . $quality . "&width=" . $width;
            if ($format !== "") {
                $sourceUrl .= "&format=" . $format;
            }
            $sourceTags .= '<source srcset="' . $sourceUrl . '" media="(min-width: ' . $width . 'px)">';
        }

        // Create the picture element
        $pictureElement = '<picture data-onik-image="true" data-onik-image-selector="' . $selector . '" data-onik-image-quality="' . $quality . '" data-onik-image-widths="' . implode(', ', $widths) . '" data-onik-original-src="' . $src . '">';
        $pictureElement .= $sourceTags;

        // Add the fallback img tag with the smallest width
        $fallbackImg = $newImgHtml;
        $fallbackImg = str_replace('<img', '<img data-onik-image="true" data-onik-image-selector="' . $selector . '" data-onik-image-quality="' . $quality . '" data-onik-image-widths="' . implode(', ', $widths) . '" data-onik-original-src="' . $src . '"', $fallbackImg);

        // Set src to the first width (smallest) for fallback or without width if no widths provided
        if (!empty($widths)) {
            $firstSourceUrl = $newSrc . "?quality=" . $quality . "&width=" . $widths[0];
        } else {
            $firstSourceUrl = $newSrc . "?quality=" . $quality;
        }
        if ($format !== "") {
            $firstSourceUrl .= "&format=" . $format;
        }
        $fallbackImg = str_replace('src="' . $src . '"', 'src="' . $firstSourceUrl . '"', $fallbackImg);

        // Remove srcset and sizes from fallback img since they're handled by source tags
        $fallbackImg = preg_replace('/\s+srcset="[^"]*"/', '', $fallbackImg);
        $fallbackImg = preg_replace('/\s+sizes="[^"]*"/', '', $fallbackImg);

        $pictureElement .= $fallbackImg;
        $pictureElement .= '</picture>';

        $newImgHtml = $pictureElement;
    } else {
        // Add data attributes for regular img processing
        $newImgHtml = str_replace('<img', '<img data-onik-image="true" data-onik-image-selector="' . $selector . '" data-onik-image-quality="' . $quality . '" data-onik-image-widths="' . implode(', ', $widths) . '" data-onik-original-src="' . $src . '"', $newImgHtml);
    }

    // Add other attributes
    if ($loading != "") {
        $newImgHtml = str_replace('<img', '<img loading="' . $loading . '"', $newImgHtml);
    }
    if ($fetchpriority != "") {
        $newImgHtml = str_replace('<img', '<img fetchpriority="' . $fetchpriority . '"', $newImgHtml);
    }
    if ($decoding != "") {
        $newImgHtml = str_replace('<img', '<img decoding="' . $decoding . '"', $newImgHtml);
    }

    // Handle setWidth and setHeight attributes
    if ($setWidth !== null) {
        $newImgHtml = str_replace('<img', '<img width="' . $setWidth . '"', $newImgHtml);
    }
    if ($setHeight !== null) {
        $newImgHtml = str_replace('<img', '<img height="' . $setHeight . '"', $newImgHtml);
    }

    // Process data-et-multi-view attribute if present
    $multiViewAttr = $imgTag->getAttribute('data-et-multi-view');
    if ($multiViewAttr) {
        $newImgHtml = updateDiviMultiViewAttributeInHtml($multiViewAttr, $location, $config, $newImgHtml);
    }

    $modifications[] = [
        'search' => $originalImgHtml,
        'replace' => $newImgHtml,
        'selector' => $selector,
        'src' => $src,
        'pattern' => $originalPattern
    ];

    return $modifications;
}

/**
 * Find the original img tag HTML in the source string, preserving formatting
 */


function findOriginalImgHtml($imgTag, $originalHtml)
{
    $src = $imgTag->getAttribute('src');
    if (!$src) {
        return false;
    }

    // Get all attributes we want to match
    $attributes = [];

    // Check for class attribute
    $class = $imgTag->getAttribute('class');
    if ($class) {
        $attributes['class'] = $class;
    }

    // Check for id attribute
    $id = $imgTag->getAttribute('id');
    if ($id) {
        $attributes['id'] = $id;
    }

    // Check for name attribute
    $name = $imgTag->getAttribute('name');
    if ($name) {
        $attributes['name'] = $name;
    }

    // Check for decoding attribute
    $decoding = $imgTag->getAttribute('decoding');
    if ($decoding) {
        $attributes['decoding'] = $decoding;
    }

    // Check for width attribute
    $width = $imgTag->getAttribute('width');
    if ($width) {
        $attributes['width'] = $width;
    }

    // Check for height attribute
    $height = $imgTag->getAttribute('height');
    if ($height) {
        $attributes['height'] = $height;
    }

    // Check for loading attribute
    $loading = $imgTag->getAttribute('loading');
    if ($loading) {
        $attributes['loading'] = $loading;
    }

    // Check for fetchpriority attribute
    $fetchpriority = $imgTag->getAttribute('fetchpriority');
    if ($fetchpriority) {
        $attributes['fetchpriority'] = $fetchpriority;
    }

    // Start with the basic img tag pattern that matches src
    // Exclude images with data-onik-image="true" attribute
    $pattern = '/<img(?![^>]*data-onik-image\s*=\s*["\']true["\'][^>]*)[^>]*src\s*=\s*["\']' . preg_quote($src, '/') . '["\'][^>]*>/i';

    // If we have additional attributes to match, create a more specific pattern
    if (!empty($attributes)) {
        $attributePatterns = [];

        foreach ($attributes as $attrName => $attrValue) {
            // Create a pattern that matches the attribute anywhere in the tag (order-independent)
            // Use positive lookahead to ensure the attribute exists anywhere before the closing >
            $attributePatterns[] = '(?=[^>]*' . preg_quote($attrName, '/') . '\s*=\s*["\']' . preg_quote($attrValue, '/') . '["\'])';
        }

        // Also add negative lookaheads for attributes that are NOT present in the DOM element
        // This helps distinguish between similar images where one has an attribute and the other doesn't
        $potentialAttributes = ['class', 'id', 'name', 'decoding', 'width', 'height', 'loading', 'fetchpriority'];
        foreach ($potentialAttributes as $attrName) {
            if (!isset($attributes[$attrName])) {
                // If the attribute is not in our list of attributes to match, ensure it's NOT present in the tag
                // We use a negative lookahead to assert that the attribute is NOT present
                $attributePatterns[] = '(?![^>]*' . preg_quote($attrName, '/') . '\s*=\s*["\'])';
            }
        }

        // Combine all attribute patterns and require them to be present (order-independent)
        $attributePattern = implode('', $attributePatterns);
        $pattern = '/<img' . $attributePattern . '[^>]*src\s*=\s*["\']' . preg_quote($src, '/') . '["\'][^>]*>/i';

        // Try the exact match first
        if (preg_match($pattern, $originalHtml, $matches)) {
            return ['match' => $matches[0], 'pattern' => $pattern];
        }
    }

    // Fall back to basic src matching
    if (preg_match($pattern, $originalHtml, $matches)) {

        return ['match' => $matches[0], 'pattern' => $pattern];
    }

    // If no match found with additional attributes, fall back to just matching src
    if (!empty($attributes)) {
        $fallbackPattern = '/<img(?![^>]*data-onik-image\s*=\s*["\']true["\'][^>]*)[^>]*src\s*=\s*["\']' . preg_quote($src, '/') . '["\'][^>]*>/i';
        if (preg_match($fallbackPattern, $originalHtml, $matches)) {
            return ['match' => $matches[0], 'pattern' => $fallbackPattern];
        }
    }

    return false;
}

/**
 * Find the original div tag HTML in the source string, preserving formatting
 */
function findOriginalDivHtml($divTag, $originalHtml)
{
    // Try to find the original <div> tag in the original HTML, matching key attributes
    // Get all attributes we want to match
    $attributes = [];

    // Check for class attribute
    $class = $divTag->getAttribute('class');
    if ($class) {
        $attributes['class'] = $class;
    }

    // Check for id attribute
    $id = $divTag->getAttribute('id');
    if ($id) {
        $attributes['id'] = $id;
    }

    // Check for data-* attributes (commonly used in builder divs)
    if ($divTag->hasAttributes()) {
        foreach ($divTag->attributes as $attr) {
            if (strpos($attr->name, 'data-') === 0) {
                $attributes[$attr->name] = $attr->value;
            }
        }
    }

    // Start with the basic div tag pattern that matches class and id
    $pattern = '/<div[^>]*';

    foreach ($attributes as $attrName => $attrValue) {
        $pattern .= '(?=[^>]*' . preg_quote($attrName, '/') . '\s*=\s*["\']' . preg_quote($attrValue, '/') . '["\'])';
    }

    $pattern .= '[^>]*>/i';

    // Try the exact match first to find the opening tag
    if (preg_match($pattern, $originalHtml, $matches, PREG_OFFSET_CAPTURE)) {
        $openingTag = $matches[0][0];
        $openingTagStart = $matches[0][1];

        // Now find the complete div including all nested content
        $completeDiv = findCompleteDivWithNestedContent($originalHtml, $openingTagStart);
        if ($completeDiv) {
            return ['match' => $completeDiv, 'pattern' => $pattern];
        }

        // Fallback to just the opening tag if we can't find the complete div
        return ['match' => $openingTag, 'pattern' => $pattern];
    }

    // If no match found, try a fallback: just match the class attribute if present
    if (isset($attributes['class'])) {
        $fallbackPattern = '/<div[^>]*class\s*=\s*["\']' . preg_quote($attributes['class'], '/') . '["\'][^>]*>/i';
        if (preg_match($fallbackPattern, $originalHtml, $matches, PREG_OFFSET_CAPTURE)) {
            $openingTag = $matches[0][0];
            $openingTagStart = $matches[0][1];

            // Find the complete div including all nested content
            $completeDiv = findCompleteDivWithNestedContent($originalHtml, $openingTagStart);
            if ($completeDiv) {
                return ['match' => $completeDiv, 'pattern' => $fallbackPattern];
            }

            return ['match' => $openingTag, 'pattern' => $fallbackPattern];
        }
    }

    // If still no match, try to match any <div> with any data-settings attribute (for Elementor, etc)
    if ($divTag->hasAttribute('data-settings')) {
        $dataSettings = $divTag->getAttribute('data-settings');
        $dataSettingsPattern = '/<div[^>]*data-settings\s*=\s*["\']' . preg_quote($dataSettings, '/') . '["\'][^>]*>/i';
        if (preg_match($dataSettingsPattern, $originalHtml, $matches, PREG_OFFSET_CAPTURE)) {
            $openingTag = $matches[0][0];
            $openingTagStart = $matches[0][1];

            // Find the complete div including all nested content
            $completeDiv = findCompleteDivWithNestedContent($originalHtml, $openingTagStart);
            if ($completeDiv) {
                return ['match' => $completeDiv, 'pattern' => $dataSettingsPattern];
            }

            return ['match' => $openingTag, 'pattern' => $dataSettingsPattern];
        }
    }

    return false;

}

/**
 * Find the complete div tag including all nested content by counting opening and closing div tags
 */
function findCompleteDivWithNestedContent($html, $openingTagStart)
{
    $divCount = 0;
    $i = $openingTagStart;
    $startPos = $openingTagStart;

    // Start by counting the first div (the opening tag we found)
    $divCount = 1;

    // Skip past the opening tag to start looking for nested content
    $tagEnd = strpos($html, '>', $i);
    if ($tagEnd !== false) {
        $i = $tagEnd + 1;
    }

    while ($i < strlen($html)) {
        $char = $html[$i];

        // Look for opening div tag
        if ($char === '<' && substr($html, $i, 4) === '<div') {
            // Make sure it's a complete opening div tag (not a closing tag)
            $tagEnd = strpos($html, '>', $i);
            if ($tagEnd !== false) {
                $tag = substr($html, $i, $tagEnd - $i + 1);
                // Check if it's a self-closing div (shouldn't happen with div, but just in case)
                if (substr($tag, -2) !== '/>') {
                    $divCount++;
                }
                $i = $tagEnd + 1;
                continue;
            }
        }

        // Look for closing div tag
        if ($char === '<' && substr($html, $i, 6) === '</div>') {
            $divCount--;
            $i += 6;

            // If we've closed all divs, we found the complete div
            if ($divCount === 0) {
                return substr($html, $startPos, $i - $startPos);
            }
            continue;
        }

        $i++;
    }

    // If we reach here, we didn't find a complete div (malformed HTML)
    return false;
}


/**
 * Find the original iframe tag HTML in the source string, preserving formatting
 */
function findOriginalIframeHtml($iframeTag, $originalHtml)
{
    $src = $iframeTag->getAttribute('src');
    if (!$src) {
        return false;
    }

    // Get all attributes we want to match
    $attributes = [];

    // Check for class attribute
    $class = $iframeTag->getAttribute('class');
    if ($class) {
        $attributes['class'] = $class;
    }

    // Check for id attribute
    $id = $iframeTag->getAttribute('id');
    if ($id) {
        $attributes['id'] = $id;
    }

    // Check for name attribute
    $name = $iframeTag->getAttribute('name');
    if ($name) {
        $attributes['name'] = $name;
    }

    // Check for width attribute
    $width = $iframeTag->getAttribute('width');
    if ($width) {
        $attributes['width'] = $width;
    }

    // Check for height attribute
    $height = $iframeTag->getAttribute('height');
    if ($height) {
        $attributes['height'] = $height;
    }

    // Check for frameborder attribute
    $frameborder = $iframeTag->getAttribute('frameborder');
    if ($frameborder) {
        $attributes['frameborder'] = $frameborder;
    }

    // Check for allowfullscreen attribute
    $allowfullscreen = $iframeTag->getAttribute('allowfullscreen');
    if ($allowfullscreen) {
        $attributes['allowfullscreen'] = $allowfullscreen;
    }

    // Handle HTML entities in src - DOMDocument decodes them, but original HTML may have them encoded
    // Replace & with a pattern that matches both & and &amp;
    $srcPattern = preg_quote($src, '/');
    $srcPattern = str_replace('&', '(?:&amp;|&)', $srcPattern);

    // Start with the basic iframe tag pattern that matches src
    $pattern = '/<iframe[^>]*src\s*=\s*["\']' . $srcPattern . '["\'][^>]*>/i';

    // If we have additional attributes to match, create a more specific pattern
    if (!empty($attributes)) {
        $attributePatterns = [];

        foreach ($attributes as $attrName => $attrValue) {
            // Create a pattern that matches the attribute anywhere in the tag (order-independent)
            // Use positive lookahead to ensure the attribute exists anywhere before the closing >
            $attributePatterns[] = '(?=[^>]*' . preg_quote($attrName, '/') . '\s*=\s*["\']' . preg_quote($attrValue, '/') . '["\'])';
        }

        // Combine all attribute patterns and require them to be present (order-independent)
        $attributePattern = implode('', $attributePatterns);
        $pattern = '/<iframe' . $attributePattern . '[^>]*src\s*=\s*["\']' . $srcPattern . '["\'][^>]*>/i';

        // Try the exact match first
        if (preg_match($pattern, $originalHtml, $matches)) {
            return ['match' => $matches[0], 'pattern' => $pattern];
        }
    }

    // Fall back to basic src matching
    if (preg_match($pattern, $originalHtml, $matches)) {
        return ['match' => $matches[0], 'pattern' => $pattern];
    }

    // If no match found with additional attributes, fall back to just matching src
    if (!empty($attributes)) {
        $fallbackPattern = '/<iframe[^>]*src\s*=\s*["\']' . $srcPattern . '["\'][^>]*>/i';
        if (preg_match($fallbackPattern, $originalHtml, $matches)) {
            return ['match' => $matches[0], 'pattern' => $fallbackPattern];
        }
    }

    return false;
}

/**
 * Collect modifications for div elements without modifying the DOM
 */
function collectDivModifications($divTag, $location, $selector, $config, $originalHtml)
{
    $modifications = [];

    // Handle style tag modifications
    $styleAttr = $divTag->getAttribute('style');
    if ($styleAttr) {
        $pattern = '/url\((.*?)\)/';
        $backgroundImage = preg_match($pattern, $styleAttr, $matches);
        if ($backgroundImage && should_alter_image_based_on_src($matches[1])) {
            $format = "auto";
            if (isset($config['format'])) {
                $format = $config['format'];
            }
            $quality = 80;
            if (isset($config['quality'])) {
                $quality = $config['quality'];
            }

            $newImageLocation = $location . rawurlencode($matches[1]) . "?quality=" . $quality . "&format=" . $format;
            $newStyle = str_replace($matches[1], $newImageLocation, $styleAttr);

            $originalDivHtml = $divTag->ownerDocument->saveHTML($divTag);
            $newDivHtml = str_replace('style="' . $styleAttr . '"', 'style="' . $newStyle . '"', $originalDivHtml);

            $modifications[] = [
                'search' => $originalDivHtml,
                'replace' => $newDivHtml,
                'selector' => $selector,
                'src' => $matches[1],
                'pattern' => $pattern
            ];
        }
    }

    // Handle data-settings modifications
    $dataSettings = $divTag->getAttribute('data-settings');
    if ($dataSettings) {
        $dataSettingsArray = json_decode($dataSettings, true);
        if ($dataSettingsArray && isset($dataSettingsArray['background_slideshow_gallery'])) {
            $images = $dataSettingsArray['background_slideshow_gallery'];

            $format = "auto";
            if (isset($config['format'])) {
                $format = $config['format'];
            }
            $quality = 80;
            if (isset($config['quality'])) {
                $quality = $config['quality'];
            }
            $width = "";
            if (isset($config['widths']) && count($config['widths']) > 0) {
                $width = $config['widths'][0] . "px";
            }

            $modified = false;
            foreach ($images as &$image) {
                $originalLocation = $image['url'];
                $newImageLocation = $location . rawurlencode($originalLocation) . "?quality=" . $quality . "&format=" . $format . "&width=" . $width;
                $image['url'] = $newImageLocation;
                $modified = true;
            }
            unset($image);

            if ($modified) {
                $dataSettingsArray['background_slideshow_gallery'] = $images;
                $newDataSettings = json_encode($dataSettingsArray, ); //JSON_UNESCAPED_SLASHES);

                // Use regex to extract the div tag from $originalHtml based on the div's id or class
                $divId = $divTag->getAttribute('id');
                $divClass = $divTag->getAttribute('class');
                $pattern = '';
                if ($divId) {
                    // Match div with specific id
                    $pattern = '/<div\b[^>]*\bid\s*=\s*["\']' . preg_quote($divId, '/') . '["\'][^>]*>.*?<\/div>/is';
                } elseif ($divClass) {
                    // Match div with specific class (first class only for simplicity)
                    $firstClass = preg_split('/\s+/', $divClass)[0];
                    $pattern = '/<div\b[^>]*\bclass\s*=\s*["\'][^"\']*' . preg_quote($divTag->getAttribute('class'), '/') . '[^"\']*["\'][^>]*>.*?<\/div>/is';
                } else {
                    // This is an unsupported case, div replacements must have a unique id or class
                    return [];
                }
                $originalDivHtml = '';
                if ($pattern && preg_match($pattern, $originalHtml, $matches)) {
                    $originalDivHtml = $matches[0];
                }

                //Using preg_match on the $originalDivHtml, find the data-settings attribute and replace it with the new data-settings
                $dataSettingsPattern = '/data-settings\s*=\s*["\']*["\']/i';
                if (preg_match($dataSettingsPattern, $originalDivHtml, $matches)) {
                    $newDivHtml = str_replace($matches[0], 'data-settings=\'' . $newDataSettings . '\'', $originalDivHtml);
                }

                $modifications[] = [
                    'search' => $originalDivHtml,
                    'replace' => $newDivHtml,
                    'selector' => $selector,
                    'src' => $originalLocation,
                    'pattern' => $pattern
                ];
            }
        }
    }

    return $modifications;
}

/**
 * Collect modifications for inline style blocks
 */
function collectInlineStyleBlockModifications($dom, $selectorWidthMapping, $location, $html)
{
    $modifications = [];

    // Find all style tags
    $styleTags = $dom->getElementsByTagName('style');

    foreach ($styleTags as $styleTag) {
        $styleContent = $styleTag->nodeValue;
        if (empty($styleContent)) {
            continue;
        }

        foreach ($selectorWidthMapping as $selector => $config) {
            // Only process if srcSwap is explicitly set to InlineStyleUrl
            if (!isset($config['srcSwap']) || $config['srcSwap'] !== 'InlineStyleUrl') {
                continue;
            }

            // Check if selector exists in the style content
            // We use a simple check first to avoid regex overhead if possible, 
            // but for exact matching we need to be careful about substrings.
            // However, CSS selectors in style blocks can be complex. 
            // We'll look for the selector followed by an opening brace.

            // Escape the selector for regex
            $escapedSelector = preg_quote($selector, '/');

            // Pattern to match the selector and the block content until the closing brace
            // This is a simplified CSS parser and might not handle all edge cases (nested blocks, media queries) perfectly
            // but should work for the user's case.
            // We match: selector { ... }
            $pattern = '/' . $escapedSelector . '\s*\{(.*?)\}/s';

            if (preg_match_all($pattern, $styleContent, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $fullMatch = $match[0];
                    $blockContent = $match[1];

                    // Now look for background-image: url(...) in the block content
                    // Handle both 'background-image' and shorthand 'background'
                    $urlPattern = '/(?:background-image|background)\s*:.*?\burl\s*\(\s*[\'"]?(.*?)[\'"]?\s*\)/i';

                    if (preg_match($urlPattern, $blockContent, $urlMatch)) {
                        $originalUrl = $urlMatch[1];

                        // Clean up URL (remove quotes if they were captured inside the group, though regex above tries to avoid it)
                        $originalUrl = trim($originalUrl, '\'"');

                        if (should_alter_image_based_on_src($originalUrl)) {
                            $format = "auto";
                            if (isset($config['format'])) {
                                $format = $config['format'];
                            }
                            $quality = 80;
                            if (isset($config['quality'])) {
                                $quality = $config['quality'];
                            }

                            // For background images, we usually just want one optimized image, not a srcset.
                            // We can use the first width if provided, or just optimize without resizing if not.
                            $width = "";
                            if (isset($config['widths']) && count($config['widths']) > 0) {
                                $width = $config['widths'][0];
                            }

                            $newImageLocation = $location . rawurlencode($originalUrl) . "?quality=" . $quality;
                            if ($format !== "") {
                                $newImageLocation .= "&format=" . $format;
                            }
                            if ($width !== "") {
                                $newImageLocation .= "&width=" . $width;
                            }

                            // Replace the URL in the full match
                            // We need to be careful to replace only the URL part
                            $newFullMatch = str_replace($originalUrl, $newImageLocation, $fullMatch);

                            $modifications[] = [
                                'search' => $fullMatch,
                                'replace' => $newFullMatch,
                                'selector' => $selector,
                                'src' => $originalUrl,
                                'pattern' => null // We use exact string replacement for the CSS block
                            ];
                        }
                    }
                }
            }
        }
    }

    return $modifications;
}

function applyConfigToImg($imgTag, $location, $config, $processedImageCount)
{
    $src = $imgTag->getAttribute('src');
    if (!should_alter_image_based_on_src($src)) {
        return;
    }
    if ($imgTag->getAttribute('data-onik-image') == 'true') {
        return;
    }

    // Handle setWidth
    $setWidth = null;
    if (isset($config['setWidth'])) {
        $setWidth = $config['setWidth'];
    }

    // Handle setHeight
    $setHeight = null;
    if (isset($config['setHeight'])) {
        $setHeight = $config['setHeight'];
    }

    // Default is no widths
    $widths = []; // Default
    if (isset($config['widths'])) {
        $widths = $config['widths'];
    } else {
        $widthFound = false;

        // Try to extract width from srcset first
        if ($imgTag->hasAttribute('srcset')) {
            $srcset = $imgTag->getAttribute('srcset');
            // Look for width descriptor like "124w"
            if (preg_match('/[\s,]([0-9]+)w/', ' ' . $srcset, $matches)) {
                $width = (int) $matches[1];
                if ($width > 0 && $width <= 10000) {
                    $widths = [$width];
                    $widthFound = true;
                }
            }
        }

        // If not found in srcset, try width attribute
        if (!$widthFound) {
            $imgWidth = $imgTag->getAttribute('width');
            if ($imgWidth && is_numeric($imgWidth) && $imgWidth > 0 && $imgWidth <= 10000) {
                $widths = [(int) $imgWidth];
            }
        }

        // Also capture height for potential future use (stored but not used in widths array)
        $imgHeight = $imgTag->getAttribute('height');
    }

    $quality = 80;
    if (isset($config['quality'])) {
        $quality = $config['quality'];
    }
    $loading = "";
    if (isset($config['loading'])) {
        $loading = $config['loading'];
    }
    $sizes = "";
    if (isset($config['sizes'])) {
        $sizes = $config['sizes'];
    }

    // Handle lazyLoadAfter logic
    $lazyLoadAfter = 0;
    if (isset($config['lazyLoadAfter'])) {
        $lazyLoadAfter = $config['lazyLoadAfter'];
    }

    // If loading is set to 'lazy' and we haven't processed enough images yet, change to 'eager'
    if ($loading === 'lazy' && $processedImageCount < $lazyLoadAfter) {
        $loading = 'eager';
    }

    // Handle fetchpriority
    $fetchpriority = "";
    if (isset($config['fetchpriority'])) {
        $fetchpriority = $config['fetchpriority'];
    }

    // Handle decoding
    $decoding = "";
    if (isset($config['decoding'])) {
        $decoding = $config['decoding'];
    }

    // Handle format
    $format = "auto";
    if (isset($config['format'])) {
        $format = $config['format'];
    }

    // Handle srcSwap
    $srcSwap = "srcSet";
    if (isset($config['srcSwap'])) {
        $srcSwap = $config['srcSwap'];
    }

    // Handle picture option
    $picture = false;
    if (isset($config['picture'])) {
        $pictureValue = $config['picture'];
        // Properly sanitize the picture flag
        if (is_string($pictureValue)) {
            $picture = strtolower(trim($pictureValue)) === 'true';
        } elseif (is_numeric($pictureValue)) {
            $picture = (bool) $pictureValue;
        } else {
            $picture = (bool) $pictureValue;
        }
    }

    $newSrc = $location . rawurlencode($src);

    $sources = [];

    foreach ($widths as $width) {
        $sourceUrl = $newSrc . "?quality=" . $quality . "&width=" . $width;
        if ($format !== "") {
            $sourceUrl .= "&format=" . $format;
        }
        $sources[] = $sourceUrl . " " . $width . "w";
    }

    $imgTag->setAttribute('data-onik-image', 'true');
    $imgTag->setAttribute('data-onik-image-quality', $quality);
    $imgTag->setAttribute('data-onik-image-widths', implode(', ', $widths));
    $imgTag->setAttribute('data-onik-original-src', $src);

    // Handle srcSwap logic
    if ($srcSwap === 'srcSet' || $srcSwap === 'srcAndSrcSet') {
        $imgTag->setAttribute('srcset', implode(', ', $sources));
        $imgTag->setAttribute('sizes', $sizes);
    }

    if ($srcSwap === 'src') {
        // Remove srcset attribute if it exists
        if ($imgTag->hasAttribute('srcset')) {
            $imgTag->removeAttribute('srcset');
        }
        // Set src to the first width (smallest)
        $firstSourceUrl = $newSrc . "?quality=" . $quality . "&width=" . $widths[0];
        if ($format !== "") {
            $firstSourceUrl .= "&format=" . $format;
        }
        $imgTag->setAttribute('src', $firstSourceUrl);
    } elseif ($srcSwap === 'srcAndSrcSet') {
        // Set src to the first width (smallest) in addition to srcset
        $firstSourceUrl = $newSrc . "?quality=" . $quality . "&width=" . $widths[0];
        if ($format !== "") {
            $firstSourceUrl .= "&format=" . $format;
        }
        $imgTag->setAttribute('src', $firstSourceUrl);
    }

    if ($loading != "") {
        $imgTag->setAttribute('loading', $loading);
    }
    if ($fetchpriority != "") {
        $imgTag->setAttribute('fetchpriority', $fetchpriority);
    }
    if ($decoding != "") {
        $imgTag->setAttribute('decoding', $decoding);
    }

    // Handle setWidth and setHeight attributes
    if ($setWidth !== null) {
        $imgTag->setAttribute('width', $setWidth);
    }
    if ($setHeight !== null) {
        $imgTag->setAttribute('height', $setHeight);
    }
}

function applyConfigToDivStyleTag($divTag, $location, $config)
{
    // The div tag needs to have a sytle tag, otherwise return
    $styleAttr = $divTag->getAttribute('style');
    if (!$styleAttr) {
        return;
    }

    //The style tag needs to have a background-image property, otherwise return
    // These are strings in the css like  url(https://dev.rental.software/mechanical-bull-rentals620/wp-content/uploads/2023/04/sno-cone-machine-good_1649461163_big-292x300.png)
    // We need to extract the url from the string
    $backgroundImage = preg_match('/url\((.*?)\)/', $styleAttr, $matches);
    if (!$backgroundImage) {
        return;
    }
    $backgroundImage = $matches[1];

    //the background-image property needs to be a valid image url, otherwise return
    if (!should_alter_image_based_on_src($backgroundImage)) {
        return;
    }

    $format = "auto";
    if (isset($config['format'])) {
        $format = $config['format'];
    }
    $quality = 80;
    if (isset($config['quality'])) {
        $quality = $config['quality'];
    }

    $newImageLocation = $location . rawurlencode($backgroundImage) . "?quality=" . $quality . "&format=" . $format;

    //replace the background-image property with the new image url
    $newStyle = str_replace($backgroundImage, $newImageLocation, $styleAttr);
    $divTag->setAttribute('style', $newStyle);
}

function applyConfigToDivDataSettings($divTag, $location, $config)
{
    // The div tag needs to have a sytle tag, otherwise return
    $dataSettings = $divTag->getAttribute('data-settings');
    if (!$dataSettings) {
        return;
    }

    $dataSettings = json_decode($dataSettings, true);
    if (!$dataSettings) {
        return;
    }

    //Get the array of images in dataSettings.background_slideshow_gallery
    $images = $dataSettings['background_slideshow_gallery'];

    $format = "auto";
    if (isset($config['format'])) {
        $format = $config['format'];
    }
    $quality = 80;
    if (isset($config['quality'])) {
        $quality = $config['quality'];
    }
    $width = "";
    if (isset($config['widths']) && count($config['widths']) > 0) {
        $width = $config['widths'][0];
        $width = $width . "px";
    }



    //Replace the urls in the array with the new image urls
    foreach ($images as &$image) {
        $originalLocation = $image['url'];
        $newImageLocation = $location . rawurlencode($originalLocation) . "?quality=" . $quality . "&format=" . $format . "&width=" . $width;
        $image['url'] = $newImageLocation;
    }
    unset($image); // break the reference
    $dataSettings['background_slideshow_gallery'] = $images;
    $divTag->setAttribute('data-settings', json_encode($dataSettings));
}

/**
 * Update data-et-multi-view attribute in HTML to convert image URLs to ONIK URLs
 * 
 * @param string $multiViewValue The HTML-encoded JSON string from the attribute
 * @param string $location The ONIK service base location
 * @param array $config The image configuration (quality, format, etc.)
 * @param string $imgHtml The img tag HTML to update
 * @return string The updated img tag HTML
 */
function updateDiviMultiViewAttributeInHtml($multiViewValue, $location, $config, $imgHtml)
{
    // HTML decode the attribute value
    $decodedJson = html_entity_decode($multiViewValue, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    
    // Decode JSON
    $jsonData = json_decode($decodedJson, true);
    
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($jsonData)) {
        // Invalid JSON, skip processing
        return $imgHtml;
    }

    // Get config values
    $quality = isset($config['quality']) ? (int) $config['quality'] : 80;
    $format = isset($config['format']) ? (string) $config['format'] : 'auto';

    // Recursively process the JSON structure to convert image URLs
    $processedData = processMultiViewJsonRecursive($jsonData, $location, $quality, $format);
    
    if ($processedData === null) {
        return $imgHtml;
    }

    // Re-encode as proper JSON with proper formatting
    // Note: We escape slashes to match the original format (e.g., https:\/\/example.com)
    $newJsonString = json_encode($processedData, JSON_UNESCAPED_UNICODE);
    
    // HTML encode for the attribute
    $newAttributeValue = htmlspecialchars($newJsonString, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    
    // Find and replace the data-et-multi-view attribute in the HTML
    // Find the start of the attribute
    $attrPattern = 'data-et-multi-view="';
    $startPos = stripos($imgHtml, $attrPattern);
    
    if ($startPos !== false) {
        $valueStart = $startPos + strlen($attrPattern);
        // Find the end of the attribute value (next unescaped quote)
        // We need to find the quote that's not part of &quot;
        $pos = $valueStart;
        $maxLen = strlen($imgHtml);
        $foundEnd = false;
        
        while ($pos < $maxLen) {
            // Check if we found a quote
            if ($imgHtml[$pos] === '"') {
                // Check if it's an escaped quote (&quot;)
                // Look back 5 characters (but not beyond start)
                $checkStart = max(0, $pos - 5);
                $beforeQuote = substr($imgHtml, $checkStart, $pos - $checkStart);
                if ($beforeQuote !== '&quot;') {
                    // Found unescaped closing quote
                    $foundEnd = true;
                    break;
                }
            }
            $pos++;
        }
        
        if ($foundEnd) {
            // Replace the attribute value
            $before = substr($imgHtml, 0, $valueStart);
            $after = substr($imgHtml, $pos);
            $imgHtml = $before . $newAttributeValue . $after;
        }
    }

    return $imgHtml;
}

/**
 * Recursively process JSON data structure to convert image URLs in src and srcset fields
 * 
 * @param mixed $data The data to process (can be array or string)
 * @param string $location The ONIK service base location
 * @param int $quality The image quality
 * @param string $format The image format
 * @return mixed The processed data with converted URLs
 */
function processMultiViewJsonRecursive($data, $location, $quality, $format)
{
    if (is_array($data)) {
        $processed = [];
        foreach ($data as $key => $value) {
            if ($key === 'src' && is_string($value)) {
                // Convert src URL
                if (should_alter_image_based_on_src($value)) {
                    $sourceUrl = $location . rawurlencode($value) . '?quality=' . $quality;
                    if ($format !== '') {
                        $sourceUrl .= '&format=' . $format;
                    }
                    $processed[$key] = $sourceUrl;
                } else {
                    $processed[$key] = $value;
                }
            } elseif ($key === 'srcset' && is_string($value)) {
                // Convert srcset URLs - parse and convert each URL while preserving width descriptors
                $parts = explode(',', $value);
                $newParts = [];
                foreach ($parts as $part) {
                    $part = trim($part);
                    if (preg_match('/^(\S+)\s+(\d+w)$/', $part, $matches)) {
                        $url = $matches[1];
                        $descriptor = $matches[2];
                        $width = (int) substr($descriptor, 0, -1);
                        
                        if (should_alter_image_based_on_src($url)) {
                            $sourceUrl = $location . rawurlencode($url) . '?quality=' . $quality . '&width=' . $width;
                            if ($format !== '') {
                                $sourceUrl .= '&format=' . $format;
                            }
                            $newParts[] = $sourceUrl . ' ' . $descriptor;
                        } else {
                            $newParts[] = $part;
                        }
                    } else {
                        // No width descriptor, try to convert as regular URL
                        if (should_alter_image_based_on_src($part)) {
                            $sourceUrl = $location . rawurlencode($part) . '?quality=' . $quality;
                            if ($format !== '') {
                                $sourceUrl .= '&format=' . $format;
                            }
                            $newParts[] = $sourceUrl;
                        } else {
                            $newParts[] = $part;
                        }
                    }
                }
                $processed[$key] = implode(', ', $newParts);
            } else {
                // Recursively process nested structures
                $processed[$key] = processMultiViewJsonRecursive($value, $location, $quality, $format);
            }
        }
        return $processed;
    }
    
    return $data;
}

function should_alter_image_based_on_src($src)
{

    $allowDomains = get_option('onik_images_allow_domains');
    $allowedDomains = explode(',', $allowDomains);

    // Get forbidden domains from settings, default to localhost and 127.0.0.1
    $forbiddenDomainsOption = get_option('onik_images_forbidden_domains');
    if (empty(trim($forbiddenDomainsOption))) {
        $forbiddenDomains = [];
    } else {
        $forbiddenDomains = array_map('trim', explode(',', $forbiddenDomainsOption));
    }

    // Remove forbidden domains that are explicitly allowed
    $forbiddenDomains = array_diff($forbiddenDomains, $allowedDomains);

    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'];
    $extension = pathinfo($src, PATHINFO_EXTENSION);
    if (!in_array($extension, $allowed_extensions)) {
        return false;
    }

    //if the src string contains one of the allowed domains, return true
    foreach ($forbiddenDomains as $forbiddenDomain) {
        if (strpos($src, $forbiddenDomain) > 0) {
            return false;
        }
    }

    if (empty($allowDomains)) {
        return true;
    }

    //if the src string contains one of the allowed domains, return true
    foreach ($allowedDomains as $allowedDomain) {
        if (strpos($src, $allowedDomain) > 0) {
            return true;
        }
    }


    return false;
}

/**
 * Helper function to query DOM elements using CSS selectors
 * 
 * @param DOMDocument $dom The DOM document to query
 * @param string $selector CSS selector string
 * @return DOMNodeList|false List of matching elements or false on error
 */
function onik_images_query_css($dom, $selector)
{
    try {
        $converter = new CssSelectorConverter();
        $xpathExpression = $converter->toXPath($selector);
        $xpath = new DOMXPath($dom);
        return $xpath->query($xpathExpression);
    } catch (Exception $e) {
        error_log('ONIK Images: CSS Selector error for "' . $selector . '": ' . $e->getMessage());
        return false;
    }
}

/**
 * JSON Schema for onik_images_image_settings
 */
function onik_images_get_image_settings_schema()
{
    $schema_path = __DIR__ . '/schema/onik-images-image-settings.json';
    if (file_exists($schema_path)) {
        $schema_json = file_get_contents($schema_path);
        $schema = json_decode($schema_json, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $schema;
        }
    }
    // Fallback: return the hardcoded schema if file is missing or invalid
    return [];
}

/**
 * Validate JSON against the preloads schema
 * 
 * @param string $json_string The JSON string to validate
 * @return array|WP_Error Array with 'valid' boolean and 'errors' array, or WP_Error on JSON parse failure
 */
function onik_images_validate_preloads($json_string)
{
    // If empty, consider it valid (optional field)
    if (empty(trim($json_string))) {
        return ['valid' => true, 'errors' => []];
    }

    // Decode JSON
    $data = json_decode($json_string, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return new WP_Error(
            'json_parse_error',
            'Invalid JSON format in Preloads: ' . json_last_error_msg()
        );
    }

    $errors = [];

    // Validate top-level structure
    if (!is_array($data)) {
        $errors[] = 'Root must be an array';
        return ['valid' => false, 'errors' => $errors];
    }

    // Validate each preload configuration
    foreach ($data as $index => $preload) {
        if (!is_array($preload)) {
            $errors[] = "Preload at index $index must be an object";
            continue;
        }

        if (isset($preload['fetchpriority'])) {
            if (!is_string($preload['fetchpriority']) || !in_array($preload['fetchpriority'], ['high', 'low'])) {
                $errors[] = "Preload at index $index fetchpriority must be 'high' or 'low'";
                continue;
            }
        }

        if (isset($preload['as'])) {
            if (!is_string($preload['as']) || empty(trim($preload['as']))) {
                $errors[] = "Preload at index $index 'as' must be a non-empty string";
            }
        }

        // Validate required 'href' field
        if (!isset($preload['href'])) {
            $errors[] = "Preload at index $index is missing required 'href' field";
            continue;
        }

        if (!is_string($preload['href']) || empty(trim($preload['href']))) {
            $errors[] = "Preload at index $index 'href' must be a non-empty string";
        }

        // Validate optional 'type' field
        if (isset($preload['type']) && !is_string($preload['type'])) {
            $errors[] = "Preload at index $index 'type' must be a string";
        }

        // Validate optional 'urlFilter' field
        if (isset($preload['urlFilter'])) {
            if (!is_string($preload['urlFilter'])) {
                $errors[] = "Preload at index $index 'urlFilter' must be a string";
            } else if (!empty(trim($preload['urlFilter']))) {
                // Test if the regex is valid (only if not empty)
                $test_regex = @preg_match($preload['urlFilter'], '');
                if ($test_regex === false) {
                    $errors[] = "Preload at index $index 'urlFilter' contains invalid regex pattern";
                }
            }
        }

        // Check for unknown properties
        $allowed_properties = ['fetchpriority', 'as', 'href', 'type', 'urlFilter', 'rel', 'crossorigin'];
        foreach ($preload as $property => $value) {
            if (!in_array($property, $allowed_properties)) {
                $errors[] = "Preload at index $index contains unknown property: '$property'";
            }
        }
    }

    return ['valid' => empty($errors), 'errors' => $errors];
}

/**
 * Validate JSON against the image settings schema
 * 
 * @param string $json_string The JSON string to validate
 * @return array|WP_Error Array with 'valid' boolean and 'errors' array, or WP_Error on JSON parse failure
 */
function onik_images_validate_image_settings($json_string)
{
    // If empty, consider it valid (optional field)
    if (empty(trim($json_string))) {
        return ['valid' => true, 'errors' => []];
    }

    // Decode JSON
    $data = json_decode($json_string, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return new WP_Error(
            'json_parse_error',
            'Invalid JSON format in Image Settings: ' . json_last_error_msg()
        );
    }

    $schema = onik_images_get_image_settings_schema();
    $errors = [];

    // Validate top-level structure
    if (!is_array($data)) {
        $errors[] = 'Root must be an object';
        return ['valid' => false, 'errors' => $errors];
    }



    // Validate each selector configuration
    foreach ($data as $selector => $config) {
        if (!is_string($selector) || empty(trim($selector))) {
            $errors[] = "Invalid selector: '$selector' - must be a non-empty string";
            continue;
        }

        if (!is_array($config)) {
            $errors[] = "Configuration for selector '$selector' must be an object";
            continue;
        }

        // Validate widths array if present (optional - can be extracted from image element)
        if (isset($config['widths'])) {
            if (!is_array($config['widths'])) {
                $errors[] = "Selector '$selector' widths must be an array";
                continue;
            }

            if (empty($config['widths'])) {
                $errors[] = "Selector '$selector' widths array cannot be empty";
                continue;
            }

            foreach ($config['widths'] as $index => $width) {
                if (!is_numeric($width) || $width < 1 || $width > 10000) {
                    $errors[] = "Selector '$selector' width at index $index must be an integer between 1 and 10000";
                }
            }
        }

        // Validate quality if present
        if (isset($config['quality'])) {
            if (!is_numeric($config['quality']) || $config['quality'] < 1 || $config['quality'] > 100) {
                $errors[] = "Selector '$selector' quality must be an integer between 1 and 100";
            }
        }

        // Validate loading if present
        if (isset($config['loading'])) {
            $valid_loading_values = ['lazy', 'eager', ''];
            if (!in_array($config['loading'], $valid_loading_values)) {
                $errors[] = "Selector '$selector' loading must be one of: " . implode(', ', $valid_loading_values);
            }
        }

        // Validate sizes if present
        if (isset($config['sizes']) && !is_string($config['sizes'])) {
            $errors[] = "Selector '$selector' sizes must be a string";
        }

        // Validate lazyLoadAfter if present
        if (isset($config['lazyLoadAfter'])) {
            if (!is_numeric($config['lazyLoadAfter']) || $config['lazyLoadAfter'] < 0) {
                $errors[] = "Selector '$selector' lazyLoadAfter must be a non-negative integer";
            }
        }

        // Validate fetchpriority if present
        if (isset($config['fetchpriority'])) {
            $valid_fetchpriority_values = ['high', 'low', 'auto', ''];
            if (!in_array($config['fetchpriority'], $valid_fetchpriority_values)) {
                $errors[] = "Selector '$selector' fetchpriority must be one of: " . implode(', ', $valid_fetchpriority_values);
            }
        }

        // Validate decoding if present
        if (isset($config['decoding'])) {
            $valid_decoding_values = ['sync', 'async', 'auto', ''];
            if (!in_array($config['decoding'], $valid_decoding_values)) {
                $errors[] = "Selector '$selector' decoding must be one of: " . implode(', ', $valid_decoding_values);
            }
        }

        // Validate format if present
        if (isset($config['format'])) {
            $valid_format_values = ['auto', 'jpg', 'jpeg', 'png', 'gif', 'avif', 'webp', ''];
            if (!in_array($config['format'], $valid_format_values)) {
                $errors[] = "Selector '$selector' format must be one of: " . implode(', ', $valid_format_values);
            }
        }

        // Validate srcSwap if present
        if (isset($config['srcSwap'])) {
            $valid_srcswap_values = ['srcSet', 'src', 'srcAndSrcSet', 'InlineStyleUrl'];
            if (!in_array($config['srcSwap'], $valid_srcswap_values)) {
                $errors[] = "Selector '$selector' srcSwap must be one of: " . implode(', ', $valid_srcswap_values);
            }
        }

        // Validate setWidth if present
        if (isset($config['setWidth'])) {
            if ($config['setWidth'] !== null && (!is_numeric($config['setWidth']) || $config['setWidth'] < 1)) {
                $errors[] = "Selector '$selector' setWidth must be null or a positive integer";
            }
        }

        // Validate setHeight if present
        if (isset($config['setHeight'])) {
            if ($config['setHeight'] !== null && (!is_numeric($config['setHeight']) || $config['setHeight'] < 1)) {
                $errors[] = "Selector '$selector' setHeight must be null or a positive integer";
            }
        }

        // Check for unknown properties
        $allowed_properties = ['widths', 'quality', 'loading', 'sizes', 'lazyLoadAfter', 'fetchpriority', 'decoding', 'format', 'srcSwap', 'setWidth', 'setHeight', 'picture'];
        foreach ($config as $property => $value) {
            if (!in_array($property, $allowed_properties)) {
                $errors[] = "Selector '$selector' contains unknown property: '$property'";
            }
        }
    }

    return ['valid' => empty($errors), 'errors' => $errors];
}

/**
 * Sanitize and validate the image settings option
 * 
 * @param string $input The input value to sanitize
 * @return string The sanitized value or original input if invalid
 */
function onik_images_sanitize_image_settings($input)
{
    // If input is an array (from our table), convert it to JSON
    if (is_array($input)) {
        $converter = new \OnikImages\SettingsConverter();
        $input = $converter->tableToJson($input);
    }

    $validation = onik_images_validate_image_settings($input);

    if (is_wp_error($validation)) {
        add_settings_error(
            'onik_images_image_settings',
            'json_parse_error',
            $validation->get_error_message(),
            'error'
        );
        // Return the original input so the form field retains the user's data
        // Note: If input was array, we returned the converted JSON string, which is better than array for the textarea fallback
        return $input;
    }

    if (!$validation['valid']) {
        $error_message = 'Invalid image settings configuration:<br>';
        foreach ($validation['errors'] as $error) {
            $error_message .= '• ' . esc_html($error) . '<br>';
        }
        add_settings_error(
            'onik_images_image_settings',
            'validation_error',
            $error_message,
            'error'
        );
        // Return the original input so the form field retains the user's data
        return $input;
    }

    // If valid, return the cleaned input
    return $input;
}

/**
 * Sanitize and validate the preloads option
 * 
 * @param string $input The input value to sanitize
 * @return string The sanitized value or original input if invalid
 */
function onik_images_sanitize_preloads($input)
{
    // If input is an array (from our table), convert it to JSON
    if (is_array($input)) {
        $converter = new \OnikImages\SettingsConverter();
        $input = $converter->tableToPreloadsJson($input);
    }

    $validation = onik_images_validate_preloads($input);

    if (is_wp_error($validation)) {
        add_settings_error(
            'onik_images_preloads',
            'json_parse_error',
            $validation->get_error_message(),
            'error'
        );
        // Return the original input so the form field retains the user's data
        return $input;
    }

    if (!$validation['valid']) {
        $error_message = 'Invalid preloads configuration:<br>';
        foreach ($validation['errors'] as $error) {
            $error_message .= '• ' . esc_html($error) . '<br>';
        }
        add_settings_error(
            'onik_images_preloads',
            'validation_error',
            $error_message,
            'error'
        );
        // Return the original input so the form field retains the user's data
        return $input;
    }

    // If valid, return the cleaned input
    return $input;
}


function onik_images_sanitize_script_block($input)
{
    // If empty, consider it valid (optional field)
    if (empty(trim($input))) {
        return $input;
    }

    // Decode JSON
    $data = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        add_settings_error(
            'onik_images_script_block',
            'json_parse_error',
            'Invalid JSON format for script_block: ' . json_last_error_msg(),
            'error'
        );
        // Return the original input so the form field retains the user's data
        return $input;
    }

    // Validate top-level structure
    if (!is_array($data)) {
        add_settings_error(
            'onik_images_script_block',
            'invalid_structure',
            'Script block configuration must be an array of configurations.',
            'error'
        );
        // Return the original input so the form field retains the user's data
        return $input;
    }

    // Validate each configuration in the array
    foreach ($data as $index => $config) {
        if (!is_array($config)) {
            add_settings_error(
                'onik_images_script_block',
                'invalid_config_' . $index,
                "Configuration at index $index must be an object.",
                'error'
            );
            continue;
        }

        // Validate 'urlPattern' field
        if (!isset($config['urlPattern']) || !is_string($config['urlPattern']) || empty(trim($config['urlPattern']))) {
            add_settings_error(
                'onik_images_script_block',
                'missing_urlPattern_' . $index,
                "Configuration at index $index is missing required 'urlPattern' field.",
                'error'
            );
            continue;
        }

        // Validate 'urlFilter' field if present
        if (isset($config['urlFilter']) && !is_string($config['urlFilter'])) {
            add_settings_error(
                'onik_images_script_block',
                'invalid_urlFilter_' . $index,
                "Configuration at index $index 'urlFilter' must be a string.",
                'error'
            );
            continue;
        }

        // Check for unknown properties
        $allowed_properties = ['urlPattern', 'urlFilter'];
        foreach ($config as $property => $value) {
            if (!in_array($property, $allowed_properties)) {
                add_settings_error(
                    'onik_images_script_block',
                    'unknown_property_' . $index . '_' . $property,
                    "Configuration at index $index contains unknown property: '$property'",
                    'error'
                );
            }
        }
    }

    // If valid, return the cleaned input
    return $input;
}


function onik_images_sanitize_enabled($input)
{
    $new = ($input === '1' || $input === 1) ? '1' : '0';
    $old = get_option('onik_images_enabled', '0');
    if ($new === '1' && $old !== '1') {
        (new \OnikImages\LensActivation())->scheduleImmediateCheck();
    }
    return $new;
}

function onik_images_sanitize_image_converter_url($input)
{
    // Check if the plugin is enabled
    $enabled = get_option('onik_images_enabled');

    // If plugin is enabled, URL cannot be empty
    if ($enabled && empty(trim($input))) {
        add_settings_error(
            'onik_images_image_converter_url',
            'empty_url',
            'Image Converter URL is required when ONIK Images is enabled.',
            'error'
        );
        // Return the original input so the form field retains the user's data
        return $input;
    }

    // If plugin is disabled, allow empty URL
    if (!$enabled) {
        return $input;
    }

    // Basic URL validation for enabled plugin
    $trimmed_input = trim($input);
    if (!empty($trimmed_input) && !filter_var($trimmed_input, FILTER_VALIDATE_URL)) {
        add_settings_error(
            'onik_images_image_converter_url',
            'invalid_url',
            'Please enter a valid URL for the Image Converter URL.',
            'error'
        );
        return $input;
    }

    // Ensure URL has trailing slash
    if (!empty($trimmed_input) && substr($trimmed_input, -1) !== '/') {
        add_settings_error(
            'onik_images_image_converter_url',
            'missing_trailing_slash',
            'Image Converter URL must end with a trailing slash (/).',
            'error'
        );
        return $input;
    }

    return $trimmed_input;
}

function onik_images_sanitize_regex_replace($input)
{
    // If empty, consider it valid (optional field)
    if (empty(trim($input))) {
        return $input;
    }

    // Decode JSON
    $data = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        add_settings_error(
            'onik_images_regex_replace',
            'json_parse_error',
            'Invalid JSON format for regex_replace: ' . json_last_error_msg(),
            'error'
        );
        // Return the original input so the form field retains the user's data
        return $input;
    }

    // Validate top-level structure
    if (!is_array($data)) {
        add_settings_error(
            'onik_images_regex_replace',
            'invalid_structure',
            'Regex replace configuration must be an array of configurations.',
            'error'
        );
        // Return the original input so the form field retains the user's data
        return $input;
    }

    // Validate each configuration in the array
    foreach ($data as $index => $config) {
        if (!is_array($config)) {
            add_settings_error(
                'onik_images_regex_replace',
                'invalid_config_' . $index,
                "Configuration at index $index must be an object.",
                'error'
            );
            continue;
        }

        // Validate 'targetKey' field
        if (!isset($config['targetKey']) || !is_string($config['targetKey']) || empty(trim($config['targetKey']))) {
            add_settings_error(
                'onik_images_regex_replace',
                'missing_targetKey_' . $index,
                "Configuration at index $index is missing required 'targetKey' field.",
                'error'
            );
            continue;
        }

        // Validate quality if present
        if (isset($config['quality'])) {
            if (!is_numeric($config['quality']) || $config['quality'] < 1 || $config['quality'] > 100) {
                add_settings_error(
                    'onik_images_regex_replace',
                    'invalid_quality_' . $index,
                    "Configuration at index $index quality must be an integer between 1 and 100.",
                    'error'
                );
            }
        }

        // Validate format if present
        if (isset($config['format'])) {
            $valid_format_values = ['auto', 'jpg', 'jpeg', 'png', 'gif', 'avif', 'webp', ''];
            if (!in_array($config['format'], $valid_format_values)) {
                add_settings_error(
                    'onik_images_regex_replace',
                    'invalid_format_' . $index,
                    "Configuration at index $index format must be one of: " . implode(', ', $valid_format_values),
                );
            }
        }

        // Validate width if present
        if (isset($config['width'])) {
            if (is_array($config['width'])) {
                if (empty($config['width'])) {
                    add_settings_error(
                        'onik_images_regex_replace',
                        'empty_width_array_' . $index,
                        "Configuration at index $index width array cannot be empty.",
                        'error'
                    );
                } else {
                    foreach ($config['width'] as $widthIndex => $width) {
                        if (!is_numeric($width) || $width < 1 || $width > 10000) {
                            add_settings_error(
                                'onik_images_regex_replace',
                                'invalid_width_' . $index . '_' . $widthIndex,
                                "Configuration at index $index width at array index $widthIndex must be an integer between 1 and 10000.",
                                'error'
                            );
                        }
                    }
                }
            } elseif (!is_numeric($config['width']) || $config['width'] < 1 || $config['width'] > 10000) {
                add_settings_error(
                    'onik_images_regex_replace',
                    'invalid_width_' . $index,
                    "Configuration at index $index width must be an integer between 1 and 10000.",
                    'error'
                );
            }
        }

        // Validate urlFilter if present
        if (isset($config['urlFilter']) && !is_string($config['urlFilter'])) {
            add_settings_error(
                'onik_images_regex_replace',
                'invalid_urlFilter_' . $index,
                "Configuration at index $index urlFilter must be a string.",
                'error'
            );
        }

        // Check for unknown properties
        $allowed_properties = ['targetKey', 'quality', 'format', 'width', 'urlFilter'];
        foreach ($config as $property => $value) {
            if (!in_array($property, $allowed_properties)) {
                add_settings_error(
                    'onik_images_regex_replace',
                    'unknown_property_' . $index . '_' . $property,
                    "Configuration at index $index contains unknown property: '$property'",
                    'error'
                );
            }
        }
    }

    // If valid, return the cleaned input
    return $input;
}

function onik_images_get_current_request_path($override = null)
{
    if ($override !== null) {
        return $override;
    }

    if (php_sapi_name() === 'cli' || !isset($_SERVER['REQUEST_URI'])) {
        return null;
    }

    return parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
}

function onik_images_patch_html($html)
{
    // Handle null input
    if ($html === null) {
        return '';
    }

    $html = str_replace('</script></div>', '</div>', $html);

    return $html;
}

/**
 * Collect YouTube embed modifications for replacement with lite-youtube-embed components
 * 
 * @param DOMDocument $dom The DOM document to process
 * @param string $originalHtml The original HTML string for finding exact matches
 * @return array Array of modification objects with 'search' and 'replace' keys
 */
function collectYouTubeModifications($dom, $originalHtml)
{
    $modifications = [];

    // Get YouTube settings
    $youtube_settings_string = get_option('onik_images_youtube_settings');
    $youtube_settings = json_decode($youtube_settings_string, true);

    // If no settings configured, use default behavior for all YouTube iframes
    if (empty($youtube_settings) || !is_array($youtube_settings)) {
        $youtube_settings = ['iframe[src*="youtube"]' => []];
    }

    // Process each selector configuration
    foreach ($youtube_settings as $selector => $config) {
        // Find iframe elements matching this selector
        $elements = onik_images_query_css($dom, $selector);

        if ($elements !== false) {
            foreach ($elements as $element) {
                $src = '';
                $original = null;
                if ($element->tagName == 'iframe') {
                    $src = $element->getAttribute('src');
                    $original = findOriginalIframeHtml($element, $originalHtml);

                }
                if ($element->tagName == 'div') {
                    $src = $element->getAttribute('data-settings');
                    $src = json_decode($src, true)['youtube_url'];

                    $original = findOriginalDivHtml($element, $originalHtml);
                }

                if (empty($src)) {
                    continue;
                }

                // Check if this is a YouTube embed
                $video_id = onik_images_extract_youtube_video_id($src);
                if (!$video_id) {
                    continue;
                }

                // Find the original iframe HTML in the source string

                if (!$original) {
                    continue;
                }

                // Create lite-youtube element with selector-specific settings
                $lite_youtube = onik_images_create_lite_youtube_element($dom, $video_id, $element, $config);
                if (!$lite_youtube) {
                    continue;
                }

                // Convert the lite-youtube element to HTML string
                $lite_youtube_html = $dom->saveHTML($lite_youtube);

                $modifications[] = [
                    'search' => $original['match'],
                    'replace' => $lite_youtube_html,
                    'selector' => $selector,
                    'src' => $src,
                    'pattern' => $original['pattern']
                ];
            }
        }
    }

    return $modifications;
}

/**
 * Process YouTube embeds and replace them with lite-youtube-embed components
 * 
 * @param DOMDocument $dom The DOM document to process
 */
function onik_images_process_youtube_embeds($dom)
{
    // This function is now deprecated in favor of the collection pattern
    // It's kept for backward compatibility but should not be used in new code
    error_log('ONIK Images: onik_images_process_youtube_embeds is deprecated. Use collectYouTubeModifications instead.');
}

/**
 * Extract YouTube video ID from various YouTube embed URLs
 * 
 * @param string $src The iframe src attribute
 * @return string|false Video ID or false if not a YouTube URL
 */
function onik_images_extract_youtube_video_id($src)
{
    // Common YouTube embed patterns
    $patterns = [
        '/youtube\.com\/embed\/([a-zA-Z0-9_-]+)/',
        '/youtube-nocookie\.com\/embed\/([a-zA-Z0-9_-]+)/',
        '/youtube\.com\/v\/([a-zA-Z0-9_-]+)/',
        '/youtu\.be\/([a-zA-Z0-9_-]+)/',
        '/youtube\.com\/watch\?v=([a-zA-Z0-9_-]+)/'
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $src, $matches)) {
            return $matches[1];
        }
    }

    return false;
}

/**
 * Create a lite-youtube element to replace a YouTube iframe
 * 
 * @param DOMDocument $dom The DOM document
 * @param string $video_id The YouTube video ID
 * @param DOMElement $original_iframe The original iframe element
 * @param array $config Optional configuration array for this specific selector
 * @return DOMElement|false The new lite-youtube element or false on failure
 */
function onik_images_create_lite_youtube_element($dom, $video_id, $original_iframe, $config = [])
{
    // Create the lite-youtube element
    $lite_youtube = $dom->createElement('lite-youtube');

    // Set the videoid attribute
    $lite_youtube->setAttribute('videoid', $video_id);

    // Copy relevant attributes from the original iframe
    $attributes_to_copy = ['width', 'height', 'style', 'class'];
    foreach ($attributes_to_copy as $attr) {
        if ($original_iframe->hasAttribute($attr)) {
            $lite_youtube->setAttribute($attr, $original_iframe->getAttribute($attr));
        }
    }

    // Apply custom settings if configured
    if (!empty($config) && is_array($config)) {
        if (isset($config['playlabel'])) {
            $lite_youtube->setAttribute('playlabel', $config['playlabel']);
        }
        if (isset($config['title'])) {
            $lite_youtube->setAttribute('title', $config['title']);
        }
        if (isset($config['params'])) {
            $lite_youtube->setAttribute('params', $config['params']);
        }
        if (isset($config['js_api']) && $config['js_api']) {
            $lite_youtube->setAttribute('js-api', '');
        }
        if (isset($config['style'])) {
            // Get existing style attribute or start with empty string
            $existing_style = $lite_youtube->hasAttribute('style') ? $lite_youtube->getAttribute('style') : '';

            // Append the custom style to the existing style attribute
            $custom_style = $config['style'];
            $new_style = $existing_style ? $existing_style . ' ' . $custom_style : $custom_style;

            $lite_youtube->setAttribute('style', $new_style);
        }
    }

    // Set default playlabel if not provided
    if (!$lite_youtube->hasAttribute('playlabel')) {
        $lite_youtube->setAttribute('playlabel', 'Play: ' . $video_id);
    }

    return $lite_youtube;
}

/**
 * Sanitize and validate the YouTube settings option
 * 
 * @param string $input The input value to sanitize
 * @return string The sanitized value or original input if invalid
 */
function onik_images_sanitize_youtube_settings($input)
{
    // If input is an array (from our table), convert it to JSON
    if (is_array($input)) {
        $converter = new \OnikImages\SettingsConverter();
        $input = $converter->tableToYoutubeJson($input);
    }

    // If empty, consider it valid (optional field)
    if (empty(trim($input))) {
        return $input;
    }

    // Decode JSON
    $data = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        add_settings_error(
            'onik_images_youtube_settings',
            'json_parse_error',
            'Invalid JSON format: ' . json_last_error_msg(),
            'error'
        );
        return $input;
    }

    $errors = [];

    // Validate structure - should be an object with CSS selectors as keys
    if (!is_array($data)) {
        $errors[] = 'Root must be an object with CSS selectors as keys';
    } else {
        // Validate each selector configuration
        foreach ($data as $selector => $config) {
            if (!is_string($selector)) {
                $errors[] = "Selector must be a string, got: " . gettype($selector);
                continue;
            }

            if (!is_array($config)) {
                $errors[] = "Configuration for selector '$selector' must be an object";
                continue;
            }

            // Validate allowed properties for each selector
            $allowed_properties = ['playlabel', 'title', 'params', 'js_api', 'style'];
            foreach ($config as $property => $value) {
                if (!in_array($property, $allowed_properties)) {
                    $errors[] = "Unknown property '$property' for selector '$selector'";
                }
            }

            // Validate js_api is boolean
            if (isset($config['js_api']) && !is_bool($config['js_api'])) {
                $errors[] = "js_api must be a boolean value for selector '$selector'";
            }

            // Validate string properties
            $string_properties = ['playlabel', 'title', 'params', 'style'];
            foreach ($string_properties as $prop) {
                if (isset($config[$prop]) && !is_string($config[$prop])) {
                    $errors[] = "$prop must be a string for selector '$selector'";
                }
            }
        }
    }

    if (!empty($errors)) {
        $error_message = 'Invalid YouTube settings configuration:<br>';
        foreach ($errors as $error) {
            $error_message .= '• ' . esc_html($error) . '<br>';
        }
        add_settings_error(
            'onik_images_youtube_settings',
            'validation_error',
            $error_message,
            'error'
        );
        return $input;
    }

    return $input;
}
