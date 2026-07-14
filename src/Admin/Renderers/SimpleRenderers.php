<?php

namespace OnikImages\Admin\Renderers;

use OnikImages\Admin\FieldHelpers;

/**
 * One-liner field renderers — section headers and small JSON-textarea fields
 * that don't need their own class file.
 */
class SimpleRenderers
{
    public static function youtubeSection(): void
    {
        echo '<p>Configure YouTube optimization settings below. This feature replaces standard YouTube embeds with lightweight lite-youtube-embed components for faster page loading.</p>';
    }

    public static function youtubeEnabled(): void
    {
        FieldHelpers::checkbox('onik_images_youtube_enabled');
        echo '<p style="font-weight: bold; color: #0073aa;">When unchecked, the plugin will have no effect on YouTube content.</p>';
    }

    public static function regexReplace(): void
    {
        FieldHelpers::textarea('onik_images_regex_replace');
        echo '<p>Enter regex replace configurations in JSON format. Each configuration should have a "targetKey" field (e.g., "rentalimage_imageloc") and optionally quality, format, width, and urlFilter. The plugin will automatically build the appropriate regex patterns to find and replace image URLs in JSON-like structures.</p>';
    }
}
