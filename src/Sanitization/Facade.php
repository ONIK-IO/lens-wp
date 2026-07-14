<?php

namespace OnikImages\Sanitization;

/**
 * Class facade over the procedural sanitizer/validator functions in
 * src/Sanitization/sanitizers.php.
 *
 * Named "Facade" (not "Sanitizers") because the macOS default filesystem is
 * case-insensitive and Sanitizers.php would collide with sanitizers.php.
 *
 * The procedural impls remain because WordPress register_setting() invokes
 * sanitize_callbacks by string name, and tests call validators directly.
 * New code should prefer this OO surface.
 */
class Facade
{
    public static function imageSettings($input)
    {
        return onik_images_sanitize_image_settings($input);
    }

    public static function preloads($input)
    {
        return onik_images_sanitize_preloads($input);
    }

    public static function enabled($input)
    {
        return onik_images_sanitize_enabled($input);
    }

    public static function imageConverterUrl($input)
    {
        return onik_images_sanitize_image_converter_url($input);
    }

    public static function regexReplace($input)
    {
        return onik_images_sanitize_regex_replace($input);
    }

    public static function youtubeSettings($input)
    {
        return onik_images_sanitize_youtube_settings($input);
    }

    public static function validatePreloads(string $jsonString): array
    {
        return onik_images_validate_preloads($jsonString);
    }

    public static function validateImageSettings(string $jsonString): array
    {
        return onik_images_validate_image_settings($jsonString);
    }

    public static function imageSettingsSchema(): array
    {
        return onik_images_get_image_settings_schema();
    }
}
