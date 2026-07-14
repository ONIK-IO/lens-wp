<?php
/**
 * Divi MultiView attribute walking — page-builder-specific JSON in
 * data-settings attributes that contains URLs at arbitrary nesting depth.
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


/**
 * Collect YouTube embed modifications for replacement with lite-youtube-embed components
 * 
 * @param DOMDocument $dom The DOM document to process
 * @param string $originalHtml The original HTML string for finding exact matches
 * @return array Array of modification objects with 'search' and 'replace' keys
 */
