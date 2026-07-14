<?php

namespace OnikImages\Rewrite\Divi;

/**
 * Class facade over Divi MultiView walking helpers in
 * src/Rewrite/divi-multiview.php.
 */
class MultiView
{
    public static function updateAttribute(string $multiViewValue, string $location, array $config, string $imgHtml): string
    {
        return updateDiviMultiViewAttributeInHtml($multiViewValue, $location, $config, $imgHtml);
    }

    public static function processJsonRecursive(array $data, string $location, ?int $quality, ?string $format): array
    {
        return processMultiViewJsonRecursive($data, $location, $quality, $format);
    }
}
