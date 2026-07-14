<?php
/**
 * Settings page template.
 *
 * Rendered by OnikImages\Admin\SettingsPage::render(). Tab dispatch,
 * settings_errors() output, and the form wrapper live here. Individual
 * fields are emitted by do_settings_sections() / do_settings_fields()
 * which delegate to renderer classes under OnikImages\Admin\Renderers.
 *
 * @var bool $advanced  Whether ?admin=1 advanced mode is on (AdvancedMode::isEnabled())
 * @var string $current_tab  Currently selected tab slug
 */

use OnikImages\LensActivation;

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

        // Activation notices
        $activation     = new LensActivation();
        $next_check     = get_option('onik_lens_activation_next_check', '');
        $status         = $activation->getStatus();
        $has_attempted  = $next_check !== '' && $next_check !== false;

        if (isset($_GET['activation-attempted']) && $_GET['activation-attempted'] === '1') {
            if ($activation->isActivated()) {
                echo '<div class="notice notice-success is-dismissible"><p><strong>Activation successful!</strong> Your ONIK Lens account is active.</p></div>';
            } else {
                $msg = esc_html($status['message'] ?: $status['reason'] ?: 'Activation failed. Please check your credentials.');
                $clear_url = esc_url(remove_query_arg('activation-attempted'));
                echo '<div class="notice notice-error is-dismissible"><p><strong>Activation failed:</strong> ' . $msg . ' <a href="' . $clear_url . '">clear</a></p></div>';
            }
        } elseif (!$activation->isActivated()) {
            // First-run or retry consent screen. The Activate button is the
            // only way to trigger the onik.io API POST — Installer::install
            // and Gate::checkIfDue intentionally do not phone home until
            // the user has clicked Activate at least once (WP.org Guideline 7).
            $nonce_field = wp_nonce_field('onik_lens_activate_action', 'onik_lens_activate_nonce', true, false);
            $action_url  = esc_url(admin_url('options-general.php?page=onik_images_settings'));
            $admin_email = (string) get_option('admin_email', '');
            $site_url    = (string) get_site_url();
            $site_name   = (string) get_bloginfo('name');
            $version     = defined('ONIK_IMAGES_VERSION') ? ONIK_IMAGES_VERSION : '';

            $heading      = $has_attempted ? 'ONIK Lens is not activated' : 'Welcome to ONIK Lens';
            $button_label = $has_attempted ? 'Retry Activation' : 'Activate ONIK Lens';
            $notice_class = $has_attempted ? 'notice-warning' : 'notice-info';
            ?>
            <div class="notice <?php echo esc_attr($notice_class); ?>" style="padding: 12px 16px;">
                <h3 style="margin-top:0;"><?php echo esc_html($heading); ?></h3>
                <?php if ($has_attempted): ?>
                    <p><strong>Last attempt:</strong>
                        <?php echo esc_html($status['message'] ?: $status['reason'] ?: 'Your account could not be verified.'); ?>
                    </p>
                <?php else: ?>
                    <p>ONIK Lens uses an external CDN service to optimize images. Before optimization can begin we need to verify your site against <a href="https://onik.io/wp/lens" target="_blank" rel="noopener">your ONIK Lens account</a>.</p>
                <?php endif; ?>

                <p><strong>Clicking Activate will send the following data to <code>app.onik.io</code> over HTTPS:</strong></p>
                <ul style="list-style: disc; margin: 4px 0 10px 24px;">
                    <li>Site URL — <code><?php echo esc_html($site_url); ?></code></li>
                    <li>Site name — <code><?php echo esc_html($site_name); ?></code></li>
                    <li>WordPress admin email — <code><?php echo esc_html($admin_email); ?></code></li>
                    <li>Plugin version</li>
                </ul>
                <p style="font-size: 13px; color: #555;">By clicking Activate you consent to this data transfer and agree to the
                    <a href="https://onik.io/wp/lens" target="_blank" rel="noopener">ONIK Lens Terms &amp; Privacy Policy</a>.
                    You can revoke at any time by deactivating the plugin; no further data will be sent.</p>

                <form method="post" action="<?php echo $action_url; ?>" style="margin-top: 8px;">
                    <input type="hidden" name="onik_lens_activate_now" value="1" />
                    <?php echo $nonce_field; ?>
                    <p style="margin-bottom:0;">
                        <input type="submit" class="button button-primary" value="<?php echo esc_attr($button_label); ?>" />
                    </p>
                </form>
            </div>
            <?php
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
            <?php if ($advanced): ?>
                <a href="?page=onik_images_settings&tab=regex_replace"
                    class="nav-tab <?php echo $current_tab === 'regex_replace' ? 'nav-tab-active' : ''; ?>">
                    Regex Replace
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
            ];

            // Listing a setting in $general_settings tells the hidden-field
            // loop below to skip it (because it should already be in the
            // visible form). Only include a setting if it is ACTUALLY
            // rendered as a visible input on the current tab — in non-
            // advanced mode tenant/site/etc are not registered as visible
            // fields (see Admin\SettingsRegistry::registerFields), so they
            // must fall through to the hidden-field preservation path or
            // WordPress will wipe them to '' on submit.
            $general_settings = ['onik_images_enabled'];

            if ($advanced) {
                $general_settings[] = 'onik_images_image_converter_url';
                $general_settings[] = 'onik_images_tenant';
                $general_settings[] = 'onik_images_site';
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
