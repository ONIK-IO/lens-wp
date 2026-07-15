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

        // Activation status. Activation is automatic — it runs on plugin
        // activation and re-checks periodically on admin page loads (see
        // Activation\Installer::onActivate and Activation\Gate::checkIfDue).
        // It sends no personal data, so there is no consent dialog or button.
        $activation = new LensActivation();
        if (!$activation->isActivated()) {
            $status = $activation->getStatus();
            $msg    = esc_html($status['message'] ?: $status['reason'] ?: 'Verifying your ONIK Lens subscription…');
            echo '<div class="notice notice-warning" style="padding: 12px 16px;">'
                . '<p><strong>ONIK Lens is not active yet.</strong> ' . $msg . '</p>'
                . '<p style="font-size:13px;color:#555;margin-bottom:0;">Activation is automatic and retries on its own. '
                . 'Rewriting stays off until your <a href="https://onik.io/wp/lens" target="_blank" rel="noopener">ONIK Lens subscription</a> is verified.</p>'
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
            <?php if ($advanced): ?>
                <a href="?page=onik_images_settings&tab=regex_replace"
                    class="nav-tab <?php echo $current_tab === 'regex_replace' ? 'nav-tab-active' : ''; ?>">
                    Regex Replace
                </a>
            <?php endif; ?>
        </h2>

        <?php
        // Connect panel: paste the ONIK site token to link this site and sync
        // tenant/site. General tab only, and rendered outside the settings
        // form below (it posts to its own admin-post handler; see AdminPanel).
        if ($current_tab === 'general') {
            \OnikImages\Connect\AdminPanel::render();
        }
        ?>

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
            // NOTE: onik_images_tenant / onik_images_site /
            // onik_images_image_converter_url are deliberately NOT listed here
            // even in advanced mode. They are display-only (set by the ONIK
            // connection, not editable), so they render no input and must fall
            // through to the hidden-field preservation path below — otherwise
            // WordPress would wipe them on submit.
            $general_settings = ['onik_images_enabled'];

            if ($advanced) {
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
