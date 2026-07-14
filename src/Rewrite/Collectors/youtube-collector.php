<?php
/**
 * YouTube iframe -> <lite-youtube> facade rewrites.
 *
 *   collectYouTubeModifications     -> entry point; returns mod tuples
 *   onik_images_process_youtube_embeds -> DOM-side replacement (legacy)
 *   onik_images_extract_youtube_video_id -> URL -> 11-char video id
 *   onik_images_create_lite_youtube_element -> build the replacement DOM node
 *   findOriginalIframeHtml          -> locate verbatim iframe bytes
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
    error_log('ONIK Lens: onik_images_process_youtube_embeds is deprecated. Use collectYouTubeModifications instead.');
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
