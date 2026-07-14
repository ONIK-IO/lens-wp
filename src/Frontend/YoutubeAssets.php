<?php

namespace OnikImages\Frontend;

/**
 * Class facade over the lite-yt-embed asset enqueue logic in
 * src/Frontend/frontend-gates.php.
 */
class YoutubeAssets
{
    public static function enqueue(): void
    {
        onik_images_enqueue_youtube_assets();
    }

    public static function pageHasYoutube(): bool
    {
        return onik_images_has_youtube_videos();
    }

    public static function widgetContent(): string
    {
        return onik_images_get_widget_content();
    }

    public static function contentHasYoutube(string $content): bool
    {
        return onik_images_content_has_youtube_videos($content);
    }
}
