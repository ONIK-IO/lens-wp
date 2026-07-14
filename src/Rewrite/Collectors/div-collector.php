<?php
/**
 * <div> / <span> (background-image) modification pipeline.
 *
 * Applies image rewrites to inline style="background-image" and Divi
 * data-settings JSON. Divi MultiView JSON walking lives in
 * src/Rewrite/divi-multiview.php.
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
