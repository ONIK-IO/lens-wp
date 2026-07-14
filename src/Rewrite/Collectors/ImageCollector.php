<?php

namespace OnikImages\Rewrite\Collectors;

/**
 * Class facade over <img> collector/finder/applier in
 * src/Rewrite/Collectors/image-collector.php.
 */
class ImageCollector
{
    public static function collect($imgTag, string $location, string $selector, array $config, int $processedImageCount, string $originalHtml): array
    {
        return collectImgModifications($imgTag, $location, $selector, $config, $processedImageCount, $originalHtml);
    }

    public static function applyConfig($imgTag, string $location, array $config, int $processedImageCount): void
    {
        applyConfigToImg($imgTag, $location, $config, $processedImageCount);
    }

    public static function findOriginalHtml($imgTag, string $originalHtml): ?string
    {
        return findOriginalImgHtml($imgTag, $originalHtml);
    }
}
