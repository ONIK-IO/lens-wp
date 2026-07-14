<?php
/**
 * Settings sanitization, validation, and schema loading.
 *
 * Each onik_images_sanitize_* function is wired into register_setting() as
 * the sanitize_callback (see src/Admin/SettingsRegistry.php). The validate_*
 * helpers run add_settings_error() against the schemas in schema/*.json.
 * Relocated from onik-images.php during the refactor.
 */

function onik_images_get_image_settings_schema()
{
    $schema_path = dirname(__DIR__, 2) . '/schema/onik-images-image-settings.json';
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
            'Image Converter URL is required when ONIK Lens is enabled.',
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

    // Reject non-http(s) schemes. FILTER_VALIDATE_URL accepts javascript:,
    // data:, file:, etc., which would let an admin point image URLs at a
    // non-CDN target. Image src/srcset ignore these schemes in modern
    // browsers but defense in depth — keep the option to http(s) only.
    if (!empty($trimmed_input)) {
        $scheme = parse_url($trimmed_input, PHP_URL_SCHEME);
        if (!in_array($scheme, ['http', 'https'], true)) {
            add_settings_error(
                'onik_images_image_converter_url',
                'invalid_scheme',
                'Image Converter URL must use http:// or https://.',
                'error'
            );
            return $input;
        }
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
