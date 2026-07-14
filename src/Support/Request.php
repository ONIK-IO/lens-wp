<?php

namespace OnikImages\Support;

/**
 * Class facade over onik_images_get_current_request_path in support.php.
 */
class Request
{
    public static function currentPath(?string $override = null): ?string
    {
        return onik_images_get_current_request_path($override);
    }
}
