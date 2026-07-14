<?php
/**
 * HTML rewriter orchestrator.
 *
 * alter_html() is the ob_start callback. alter_html_hybrid() is the active
 * pipeline: DOM-for-discovery, str_replace-for-mutation (with preg_replace
 * fallback). All the heavy lifting is delegated to collect*Modifications()
 * helpers in src/Rewrite/Collectors/*.
 *
 * Also contains buildPreloadLinkTags (head injection).
 */

function alter_html($html, $current_path_override = null)
{
    return alter_html_hybrid($html, $current_path_override);
}

/**
 * True when an element opts out of rewriting: it, or its immediate parent, has
 * class onik-ignore. Both checks are load-bearing — see CLAUDE.md.
 */
function onik_images_element_is_ignored($element)
{
    $elementClasses = preg_split('/\s+/', trim($element->getAttribute('class')));
    if (in_array('onik-ignore', $elementClasses)) {
        return true;
    }

    $parentNode = $element->parentNode;
    if ($parentNode && $parentNode->nodeType === XML_ELEMENT_NODE) {
        $parentClasses = preg_split('/\s+/', trim($parentNode->getAttribute('class')));
        if (in_array('onik-ignore', $parentClasses)) {
            return true;
        }
    }

    return false;
}

/**
 * Locate the verbatim source bytes for an element, using the same helper the
 * corresponding collector would use, so a mask covers exactly the bytes a
 * modification's 'search' would target. Returns null for anything unhandled.
 */
function onik_images_find_original_source($element, $html)
{
    switch ($element->tagName) {
        case 'img':
            $found = findOriginalImgHtml($element, $html);
            break;
        case 'div':
        case 'span':
            $found = findOriginalDivHtml($element, $html);
            break;
        case 'iframe':
            $found = findOriginalIframeHtml($element, $html);
            break;
        default:
            return null;
    }

    $match = is_array($found) ? ($found['match'] ?? null) : null;

    return (is_string($match) && $match !== '') ? $match : null;
}

/**
 * Hide onik-ignore elements from the replace loop.
 *
 * Ignored elements are skipped at collection time, but modifications are applied
 * with str_replace(), which has no occurrence limit. When an ignored element's
 * source bytes are identical to a non-ignored element elsewhere on the page — the
 * same image in two figures, one ignored and one not — the non-ignored element's
 * modification rewrites its ignored twin as collateral, silently defeating
 * onik-ignore. Masking the ignored bytes before the loop makes them unaddressable;
 * alter_html_hybrid restores them afterwards.
 *
 * Identical snippets cannot be told apart by content, so masking is positional: the
 * Nth occurrence of a snippet in the source is the Nth element carrying that snippet
 * in document order.
 *
 * @param string      $html   Working HTML. Returned masked.
 * @param DOMDocument $dom    Discovery DOM for $html.
 * @param array       $masks  Out param: placeholder => original bytes, for restoring.
 */
function onik_images_mask_ignored_elements($html, $dom, &$masks)
{
    $masks = [];

    // Cheap bail — no page without the class needs any of this.
    if (strpos($html, 'onik-ignore') === false) {
        return $html;
    }

    $xpath = new DOMXPath($dom);
    $candidates = $xpath->query('//img | //iframe | //div | //span');
    if ($candidates === false || $candidates->length === 0) {
        return $html;
    }

    // Resolving source bytes is the expensive part (a scan of $html per element), so
    // only do it for tags that actually have an ignored instance on this page.
    $ignoredTags = [];
    foreach ($candidates as $element) {
        if (onik_images_element_is_ignored($element)) {
            $ignoredTags[$element->tagName] = true;
        }
    }

    if (empty($ignoredTags)) {
        return $html;
    }

    // snippet => ignored-flag per element carrying it, in document order.
    $snippets = [];
    foreach ($candidates as $element) {
        if (!isset($ignoredTags[$element->tagName])) {
            continue;
        }

        $snippet = onik_images_find_original_source($element, $html);
        if ($snippet === null) {
            continue;
        }

        $snippets[$snippet][] = onik_images_element_is_ignored($element);
    }

    $ranges = [];
    foreach ($snippets as $snippet => $ignoredFlags) {
        if (!in_array(true, $ignoredFlags, true)) {
            continue;
        }

        // Positional alignment only holds if each element carrying this snippet accounts
        // for exactly one occurrence of it in the source. If the counts disagree — the
        // bytes also appear inside a script or a comment, or a helper matched a sibling's
        // markup — the occurrences can't be told apart, so leave them all alone rather
        // than mask the wrong one. That falls back to the old behaviour, never worse.
        if (substr_count($html, $snippet) !== count($ignoredFlags)) {
            continue;
        }

        $offset = 0;
        foreach ($ignoredFlags as $isIgnored) {
            $pos = strpos($html, $snippet, $offset);
            if ($pos === false) {
                break;
            }
            if ($isIgnored) {
                $ranges[] = ['start' => $pos, 'length' => strlen($snippet)];
            }
            $offset = $pos + strlen($snippet);
        }
    }

    if (empty($ranges)) {
        return $html;
    }

    // A div's snippet spans its whole subtree, so an ignored img inside an ignored div
    // is covered twice. Keep the outermost range and drop what nests inside it.
    usort($ranges, function ($a, $b) {
        return ($a['start'] <=> $b['start']) ?: ($b['length'] <=> $a['length']);
    });

    $outermost = [];
    foreach ($ranges as $range) {
        $last = end($outermost);
        if ($last !== false && $range['start'] < $last['start'] + $last['length']) {
            continue;
        }
        $outermost[] = $range;
    }

    // Back to front, so the offsets of ranges not yet masked stay valid.
    $token = 0;
    foreach (array_reverse($outermost) as $range) {
        $placeholder = '<!--onik-ignore-mask-' . $token++ . '-->';
        $masks[$placeholder] = substr($html, $range['start'], $range['length']);
        $html = substr_replace($html, $placeholder, $range['start'], $range['length']);
    }

    return $html;
}

/**
 * Put the onik-ignore elements masked by onik_images_mask_ignored_elements back,
 * verbatim.
 */
function onik_images_unmask_ignored_elements($html, $masks)
{
    if (empty($masks)) {
        return $html;
    }

    return str_replace(array_keys($masks), array_values($masks), $html);
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
            error_log('ONIK Lens: Invalid JSON in regex_replace setting: ' . json_last_error_msg());
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

    if (empty($selectorWidthMapping) && empty($preloads) && empty($regexConfigs)) {
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

                    // Skip if this element, or its immediate parent, opts out
                    if (onik_images_element_is_ignored($element)) {
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
                        error_log('ONIK Lens: Unsupported element type: ' . $element->tagName);
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

    // Make onik-ignore elements unaddressable before anything replaces anything, so a
    // modification collected for an identical non-ignored twin cannot rewrite them.
    $ignoreMasks = [];
    $modifiedHtml = onik_images_mask_ignored_elements($modifiedHtml, $dom, $ignoreMasks);

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

    // Ignored elements go back exactly as they came in.
    $modifiedHtml = onik_images_unmask_ignored_elements($modifiedHtml, $ignoreMasks);


    $endTime = microtime(true);

    $alterHtmlExecutionMS = ($endTime - $startTime);
    // Add debug comment if enabled
    if ($debug) {
        $debugComment = '<!-- ONIK Lens
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
