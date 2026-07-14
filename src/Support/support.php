<?php
/**
 * Leaf utilities used across the rewriter and admin layers.
 *
 *   extractWidthsFromSizes($sizesAttribute) -> int[]
 *   should_alter_image_based_on_src($src)    -> bool
 *   onik_images_query_css($dom, $selector)   -> DOMNodeList|false
 *   onik_images_get_current_request_path($override = null) -> string|null
 *   onik_images_patch_html($html)            -> string
 *
 * These remain procedural because the test suite (Brain Monkey) mocks them
 * by global name — see ApplyConfigToDivTest etc. The class facades in
 * src/Support/{Html, Request, SizesParser, SrcGate}.php delegate here so
 * new code can use a clean OO surface while keeping mockability intact.
 */

use Symfony\Component\CssSelector\CssSelectorConverter;

function extractWidthsFromSizes($sizesAttribute)
{
    if (empty($sizesAttribute)) {
        return [];
    }

    $widths = [];
    if (preg_match_all('/(\d+)px/', $sizesAttribute, $matches)) {
        foreach ($matches[1] as $width) {
            $widthInt = (int) $width;
            if ($widthInt > 0 && $widthInt <= 10000) {
                $widths[] = $widthInt;
                $doubleWidth = $widthInt * 2;
                if ($doubleWidth <= 10000) {
                    $widths[] = $doubleWidth;
                }
            }
        }
    }

    $widths = array_unique($widths);
    sort($widths);
    return $widths;
}

function should_alter_image_based_on_src($src)
{
    $allowDomains = get_option('onik_images_allow_domains');
    $allowedDomains = explode(',', $allowDomains);

    $forbiddenDomainsOption = get_option('onik_images_forbidden_domains');
    if (empty(trim($forbiddenDomainsOption))) {
        $forbiddenDomains = [];
    } else {
        $forbiddenDomains = array_map('trim', explode(',', $forbiddenDomainsOption));
    }

    // Explicit allows override forbidden entries.
    $forbiddenDomains = array_diff($forbiddenDomains, $allowedDomains);

    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'];
    $extension = pathinfo($src, PATHINFO_EXTENSION);
    if (!in_array($extension, $allowed_extensions)) {
        return false;
    }

    foreach ($forbiddenDomains as $forbiddenDomain) {
        if (strpos($src, $forbiddenDomain) > 0) {
            return false;
        }
    }

    if (empty($allowDomains)) {
        return true;
    }

    foreach ($allowedDomains as $allowedDomain) {
        if (strpos($src, $allowedDomain) > 0) {
            return true;
        }
    }

    return false;
}

function onik_images_query_css($dom, $selector)
{
    try {
        $converter = new CssSelectorConverter();
        $xpathExpression = $converter->toXPath($selector);
        $xpath = new DOMXPath($dom);
        return $xpath->query($xpathExpression);
    } catch (Exception $e) {
        error_log('ONIK Lens: CSS Selector error for "' . $selector . '": ' . $e->getMessage());
        return false;
    }
}

function onik_images_get_current_request_path($override = null)
{
    if ($override !== null) {
        return $override;
    }

    if (php_sapi_name() === 'cli' || !isset($_SERVER['REQUEST_URI'])) {
        return null;
    }

    return parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
}

function onik_images_patch_html($html)
{
    if ($html === null) {
        return '';
    }

    return str_replace('</script></div>', '</div>', $html);
}
