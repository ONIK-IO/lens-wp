<?php
/**
 * Regex-based modifications operating on the entire HTML document.
 * Used for replacing image URLs embedded in JSON-like blobs that DOM
 * selectors cannot reach. Configured via onik_images_regex_replace.
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

/**
 * Collect modifications for an img element without modifying the DOM
 */
