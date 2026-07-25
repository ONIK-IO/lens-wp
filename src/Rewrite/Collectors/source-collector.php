<?php
/**
 * <picture><source> rewriting.
 *
 * A <picture>'s <source> candidates outrank its <img>: the browser takes the
 * first <source> whose media query matches and never consults <img src> unless
 * none do. Rewriting only the <img> therefore optimizes nothing for any viewport
 * a <source> claims — Spectra's image gallery, for one, emits desktop/tablet
 * <source> tags and leaves the mobile size on the <img>, so every non-mobile
 * visitor was loading the untouched origin file.
 *
 * Collected as modification tuples like every other collector: discovery through
 * the DOM, mutation against the original bytes.
 *
 * IMPORTANT — libxml does not know <source> is a void element. Given
 *   <picture><source A><source B><img></picture>
 * it builds picture > sourceA > sourceB > img, not four siblings. Nothing here
 * may assume parentNode is the <picture>; use onik_images_enclosing_picture().
 */

/**
 * The <picture> an element sits in, or null.
 *
 * Walks up through the bogus <source> nesting libxml produces (see file header),
 * and stops at the first <video>/<audio> so their <source> children — which
 * carry src + type="video/mp4" — can never be mistaken for image candidates.
 *
 * @param DOMNode $element
 * @return DOMElement|null
 */
function onik_images_enclosing_picture($element)
{
    $node = $element->parentNode;

    while ($node && $node->nodeType === XML_ELEMENT_NODE) {
        $tagName = strtolower($node->tagName);

        if ($tagName === 'picture') {
            return $node;
        }

        // Only the parse-artifact nesting is transparent. Anything else means the
        // element is not a direct child of a <picture>.
        if ($tagName !== 'source') {
            return null;
        }

        $node = $node->parentNode;
    }

    return null;
}

/**
 * Every <source> carrying a srcset inside the <picture> that contains $element.
 *
 * Used to sweep an <img>'s sibling sources once the <img> itself has matched a
 * selector, so existing installs get picture coverage without a settings change.
 *
 * @param DOMElement $element Typically the matched <img>.
 * @return DOMElement[]
 */
function onik_images_picture_sources($element)
{
    $picture = onik_images_enclosing_picture($element);
    if ($picture === null) {
        return [];
    }

    $sources = [];
    // Descendants, not children — the libxml nesting means only the first
    // <source> is ever a direct child of the <picture>.
    $xpath = new DOMXPath($picture->ownerDocument);
    $found = $xpath->query('.//source', $picture);
    if ($found === false) {
        return [];
    }

    foreach ($found as $source) {
        if ($source->hasAttribute('srcset')) {
            $sources[] = $source;
        }
    }

    return $sources;
}

/**
 * Build the modification tuple that points a <source>'s srcset at the converter.
 *
 * @param DOMElement $sourceTag
 * @param string     $location     Converter base, "{url}{tenant}/{site}/".
 * @param string     $selector     Selector this config came from (debug attribute).
 * @param array      $config       Same per-selector config the <img> uses.
 * @param string     $originalHtml Unmodified document, for locating source bytes.
 * @return array
 */
function collectSourceModifications($sourceTag, $location, $selector, $config, $originalHtml)
{
    if (onik_images_enclosing_picture($sourceTag) === null) {
        return [];
    }

    if ($sourceTag->getAttribute('data-onik-source') === 'true') {
        return [];
    }

    $srcset = $sourceTag->getAttribute('srcset');
    if (trim($srcset) === '') {
        return [];
    }

    $candidates = onik_images_parse_srcset($srcset);
    if (empty($candidates)) {
        return [];
    }

    $quality = isset($config['quality']) ? $config['quality'] : 80;
    $format = isset($config['format']) ? $config['format'] : 'auto';

    $newCandidates = [];
    foreach ($candidates as $candidate) {
        // A srcset is one image at several sizes and the browser is free to pick
        // any of them, so a partially rewritten list is worse than none — one
        // disallowed URL disqualifies the whole element.
        if (!should_alter_image_based_on_src($candidate['url'])) {
            return [];
        }

        $newUrl = $location . rawurlencode($candidate['url']) . '?quality=' . $quality;

        // Width comes from the candidate's own w descriptor and nowhere else.
        // In particular NOT from media="(min-width: 1024px)" — that is a viewport
        // width, not a layout width, and treating it as one upscales a 500px
        // gallery thumbnail to 1024px and ships a bigger file than the original.
        if (preg_match('/^(\d+)w$/', $candidate['descriptor'], $matches)) {
            $width = (int) $matches[1];
            if ($width > 0 && $width <= 10000) {
                $newUrl .= '&width=' . $width;
            }
        }

        if ($format !== '') {
            $newUrl .= '&format=' . $format;
        }

        $newCandidates[] = $candidate['descriptor'] !== ''
            ? $newUrl . ' ' . $candidate['descriptor']
            : $newUrl;
    }

    $original = findOriginalSourceTagHtml($sourceTag, $originalHtml);
    if (!$original) {
        return [];
    }

    $originalSourceHtml = $original['match'];
    $newSourceHtml = onik_images_replace_srcset_attribute($originalSourceHtml, implode(', ', $newCandidates));

    // A WebP plugin's type="image/webp" stops describing what comes back once the
    // converter negotiates the format per request, and the browser selects on that
    // attribute. Drop it when the format is negotiated; pin it when it is fixed.
    if ($sourceTag->hasAttribute('type')) {
        if ($format === 'auto' || $format === '') {
            $newSourceHtml = preg_replace('/\s+type\s*=\s*(["\'])[^"\']*\1/i', '', $newSourceHtml, 1);
        } else {
            $newSourceHtml = preg_replace_callback(
                '/(\s+type\s*=\s*)(["\'])[^"\']*\2/i',
                function ($matches) use ($format) {
                    return $matches[1] . $matches[2] . 'image/' . $format . $matches[2];
                },
                $newSourceHtml,
                1
            );
        }
    }

    // Marker doubles as the idempotency guard in findOriginalSourceTagHtml.
    $markers = '<source data-onik-source="true"'
        . ' data-onik-source-selector="' . htmlspecialchars($selector, ENT_QUOTES) . '"'
        . ' data-onik-source-quality="' . htmlspecialchars((string) $quality, ENT_QUOTES) . '"';
    $newSourceHtml = str_replace('<source', $markers, $newSourceHtml);

    return [[
        'search' => $originalSourceHtml,
        'replace' => $newSourceHtml,
        'selector' => $selector,
        'src' => $srcset,
        'pattern' => $original['pattern'],
    ]];
}

/**
 * Swap a single tag's srcset value, preserving the original quote style.
 *
 * Done by callback rather than str_replace on the DOM-reported value: entity
 * references (&amp; in a query string) mean the parsed value and the source bytes
 * are not always the same string.
 */
function onik_images_replace_srcset_attribute($tagHtml, $newSrcsetValue)
{
    return preg_replace_callback(
        '/(\ssrcset\s*=\s*)(["\'])[^"\']*\2/i',
        function ($matches) use ($newSrcsetValue) {
            return $matches[1] . $matches[2] . $newSrcsetValue . $matches[2];
        },
        $tagHtml,
        1
    );
}

/**
 * Find the verbatim source bytes of a <source> tag.
 *
 * Sibling <source> tags in one <picture> routinely carry byte-identical srcset
 * values and differ only by media — that is exactly what Spectra emits for its
 * desktop and tablet breakpoints. Matching on srcset alone would hand back the
 * first tag's bytes for every sibling, so the same modification would be
 * collected twice and the later tags never rewritten. The disambiguating
 * attributes are therefore load-bearing, present *and* absent alike.
 */
function findOriginalSourceTagHtml($sourceTag, $originalHtml)
{
    $srcset = $sourceTag->getAttribute('srcset');
    if ($srcset === '') {
        return false;
    }

    $disambiguators = ['media', 'type', 'sizes', 'width', 'height'];

    $attributes = [];
    foreach ($disambiguators as $name) {
        $value = $sourceTag->getAttribute($name);
        if ($value !== '') {
            $attributes[$name] = $value;
        }
    }

    $notProcessed = '(?![^>]*data-onik-source\s*=\s*["\']true["\'])';
    $srcsetMatch = '[^>]*srcset\s*=\s*["\']' . preg_quote($srcset, '/') . '["\']';

    if (!empty($attributes)) {
        $lookaheads = '';
        foreach ($attributes as $name => $value) {
            $lookaheads .= '(?=[^>]*' . preg_quote($name, '/') . '\s*=\s*["\']' . preg_quote($value, '/') . '["\'])';
        }
        foreach ($disambiguators as $name) {
            if (!isset($attributes[$name])) {
                $lookaheads .= '(?![^>]*' . preg_quote($name, '/') . '\s*=\s*["\'])';
            }
        }

        $pattern = '/<source' . $notProcessed . $lookaheads . $srcsetMatch . '[^>]*>/i';
        if (preg_match($pattern, $originalHtml, $matches)) {
            return ['match' => $matches[0], 'pattern' => $pattern];
        }
    }

    $fallbackPattern = '/<source' . $notProcessed . $srcsetMatch . '[^>]*>/i';
    if (preg_match($fallbackPattern, $originalHtml, $matches)) {
        return ['match' => $matches[0], 'pattern' => $fallbackPattern];
    }

    return false;
}
