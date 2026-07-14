<?php
/**
 * Inline <style> block rewrites. Scans CSS rules and replaces URLs that
 * match configured selectors with ONIK converter URLs.
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

