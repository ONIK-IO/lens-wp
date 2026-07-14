<?php

namespace OnikImages\Rewrite\Collectors;

/**
 * Class facade over the YouTube iframe -> <lite-youtube> pipeline in
 * src/Rewrite/Collectors/youtube-collector.php.
 */
class YoutubeCollector
{
    public static function collect($dom, string $originalHtml): array
    {
        return collectYouTubeModifications($dom, $originalHtml);
    }

    public static function processEmbeds($dom): void
    {
        onik_images_process_youtube_embeds($dom);
    }

    public static function extractVideoId(string $src): ?string
    {
        return onik_images_extract_youtube_video_id($src);
    }

    public static function createLiteYoutubeElement($dom, string $videoId, $originalIframe, array $config = [])
    {
        return onik_images_create_lite_youtube_element($dom, $videoId, $originalIframe, $config);
    }

    public static function findOriginalIframeHtml($iframeTag, string $originalHtml): ?string
    {
        return findOriginalIframeHtml($iframeTag, $originalHtml);
    }
}
