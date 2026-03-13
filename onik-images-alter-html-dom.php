function alter_html_dom($html)
{
    if (empty($html)) {
        return $html;
    }

    if (strlen($html) < 10) {
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

    $tenant = get_option('onik_images_tenant');
    $site = get_option('onik_images_site');
    $appendLocation = $trimmed_url . $tenant . '/' . $site . '/';
    $debug = get_option('onik_images_debug');
    $selectorWidthMappingString = get_option('onik_images_image_settings');
    $selectorWidthMapping = json_decode($selectorWidthMappingString, true);
    $preloadsString = get_option('onik_images_preloads');
    $preloads = json_decode($preloadsString, true);
    $htmlPatchesString = get_option('onik_images_html_patches');
    $htmlPatches = json_decode($htmlPatchesString, true);

    if (empty($selectorWidthMapping) && empty($preloads) && empty($htmlPatches)) {
        return $html;
    }

    // Parse the html into a DOMDocument
    $libxml_previous_state = libxml_use_internal_errors(true);


    $dom = new DOMDocument();
    $dom->loadHTML($html,  LIBXML_HTML_NODEFDTD |LIBXML_HTML_NOIMPLIED | LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_NONET);




   // $dom = Dom\HTMLDocument::createFromString($html);
    $domErrors = $errors = libxml_get_errors();
    libxml_clear_errors();
    libxml_use_internal_errors($libxml_previous_state);

    // Track processed images per selector for lazy loading logic
    $processedImageCounts = [];

    // Iterate over all of the elements in the selectorWidthMapping
    foreach ($selectorWidthMapping as $selector => $config) {
        $elements = onik_images_query_css($dom, $selector);

        if ($elements !== false) {
            // Initialize counter for this selector if not exists
            if (!isset($processedImageCounts[$selector])) {
                $processedImageCounts[$selector] = 0;
            }

            foreach ($elements as $element) {
                if ($element->tagName == 'img') {
                    applyConfigToImg($element, $appendLocation, $config, $processedImageCounts[$selector]);
                    $processedImageCounts[$selector]++;
                } 
                else if ($element->tagName == 'div') {
                    applyConfigToDivStyleTag($element, $appendLocation, $config);
                    applyConfigToDivDataSettings($element, $appendLocation, $config);
                }
                else {
                    //TODO: Handle other elements
                    error_log('ONIK Images: Unsupported element type: ' . $element->tagName);
                }
            }
        }
    }

    $appliedPreloads = 0;
    // Inject preloads into the head if they exist
    if (!empty($preloads)) {
        $appliedPreloads = injectPreloadsIntoHead($dom, $preloads);
    }

    // Apply HTML patches if they exist
    if (!empty($htmlPatches)) {
        foreach ($htmlPatches as $patch) {
            $search = $patch['search'];
            $replace = $patch['replace'];
            $html = str_replace($search, $replace, $html);
        }
    }

    if ($debug) {
        $dom->appendChild($dom->createComment('ONIK Images
Timestamp: ' . date("h:i:s") . '
Current Path: ' . onik_images_get_current_request_path() . '
Tenant: ' . $tenant . '
Site: ' . $site . '
Image Converter URL: ' . $trimmed_url . '
Image Settings: ' . json_encode($selectorWidthMapping) . '
Image Settings String: ' . $selectorWidthMappingString . '
Preloads: ' . json_encode($preloads) . '
Append Location: ' . $appendLocation . '
Processed Image Counts: ' . json_encode($processedImageCounts) . '
Applied Preloads: ' . $appliedPreloads . '

LIBXML_VERSION: ' . LIBXML_VERSION . '
LIBXML_HTML_NOIMPLIED: ' . LIBXML_HTML_NOIMPLIED . '
LIBXML_NOWARNING: ' . LIBXML_NOWARNING . '
LIBXML_NOERROR: ' . LIBXML_NOERROR . '
LIBXML_NONET: ' . LIBXML_NONET . '
LIBXML_HTML_NODEFDTD: ' . LIBXML_HTML_NODEFDTD 

));

    }
    $outputHtml = $dom->saveHTML();
    return onik_images_patch_html($outputHtml);

}


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