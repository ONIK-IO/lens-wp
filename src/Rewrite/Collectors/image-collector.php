<?php
/**
 * <img> tag modification pipeline.
 *
 *   collectImgModifications -> entry: builds modification tuples
 *   applyConfigToImg        -> mutates a cloned DOM element per config
 *   findOriginalImgHtml     -> locates verbatim source bytes for str_replace
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

