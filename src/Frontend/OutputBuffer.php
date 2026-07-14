<?php

namespace OnikImages\Frontend;

/**
 * Class facade over the template_redirect ob_start gate in
 * src/Frontend/frontend-gates.php.
 */
class OutputBuffer
{
    public static function register(): void
    {
        onik_images_register_ob_start();
    }
}
