<?php

namespace OnikImages\Support;

use DOMDocument;
use DOMNodeList;

/**
 * Class facade over the procedural HTML/DOM helpers in support.php.
 *
 * The procedural impls are the mockable units (Brain Monkey hooks them by
 * name). New code should prefer this class API; tests can use either.
 */
class Html
{
    /**
     * @return DOMNodeList|false
     */
    public static function queryCss(DOMDocument $dom, string $selector)
    {
        return onik_images_query_css($dom, $selector);
    }

    public static function patch(?string $html): string
    {
        return onik_images_patch_html($html);
    }
}
