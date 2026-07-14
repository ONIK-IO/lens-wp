<?php
/**
 * Frontend hook entry points.
 *
 *   onik_images_register_ob_start() — template_redirect gate that decides
 *     whether to wrap page output in alter_html. Skips admin, AJAX, JSON,
 *     /wp-json/, and any request where Lens is not activated.
 *
 *   onik_images_enqueue_youtube_assets() and the trio of has/get/content
 *     helpers — wp_enqueue_scripts callback that only ships the
 *     lite-yt-embed CSS/JS when the page actually contains a YouTube embed.
 *
 * Relocated from onik-images.php during the refactor.
 */

function onik_images_has_youtube_videos()
{
    global $wp_query;

    // If we're in the admin or not on a frontend page, return false
    if (is_admin() || !$wp_query->is_main_query()) {
        return false;
    }

    // Get the current post/page content
    $content = '';
    if (is_singular()) {
        $post = get_queried_object();
        if ($post && isset($post->post_content)) {
            $content = $post->post_content;
        }
    } elseif (is_home() || is_archive()) {
        // For archive pages, we'd need to check multiple posts
        // This is a simplified check - you might want to expand this
        $posts = get_posts(array(
            'numberposts' => 10,
            'post_status' => 'publish'
        ));
        foreach ($posts as $post) {
            $content .= $post->post_content . ' ';
        }
    }

    // Also check widget areas and other content sources
    $content .= onik_images_get_widget_content();

    // Check for YouTube video patterns in the content
    return onik_images_content_has_youtube_videos($content);
}

/**
 * Get content from widgets that might contain YouTube videos
 * 
 * @return string Widget content
 */
function onik_images_get_widget_content()
{
    $widget_content = '';

    // Check common widget areas
    $widget_areas = array('sidebar-1', 'footer-1', 'footer-2', 'footer-3');

    foreach ($widget_areas as $area) {
        if (is_active_sidebar($area)) {
            ob_start();
            dynamic_sidebar($area);
            $widget_content .= ob_get_clean() . ' ';
        }
    }

    return $widget_content;
}

/**
 * Check if content contains YouTube videos by looking for YouTube URL patterns
 * 
 * @param string $content The content to check
 * @return bool True if YouTube videos are found, false otherwise
 */
function onik_images_content_has_youtube_videos($content)
{
    if (empty($content)) {
        return false;
    }

    // YouTube URL patterns to look for
    $patterns = [
        '/youtube\.com\/embed\//',
        '/youtube-nocookie\.com\/embed\//',
        '/youtube\.com\/v\//',
        '/youtu\.be\//',
        '/youtube\.com\/watch\?v=/'
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $content)) {
            return true;
        }
    }

    return false;
}

/**
 * Enqueue lite-youtube-embed assets when YouTube optimization is enabled AND videos are detected
 */
function onik_images_enqueue_youtube_assets()
{
    $youtube_enabled = get_option('onik_images_youtube_enabled');
    if (!$youtube_enabled) {
        return;
    }

    // Only enqueue assets if YouTube videos are detected on the page
    if (!onik_images_has_youtube_videos()) {
        return;
    }

    // Enqueue lite-youtube-embed CSS
    wp_enqueue_style(
        'lite-youtube-embed',
        plugin_dir_url(\OnikImages\Plugin::pluginFile()) . 'assets/lite-yt-embed.css',
        array(),
        '0.3.3'
    );

    // Enqueue lite-youtube-embed JavaScript
    wp_enqueue_script(
        'lite-youtube-embed',
        plugin_dir_url(\OnikImages\Plugin::pluginFile()) . 'assets/lite-yt-embed.js',
        array(),
        '0.3.3',
        true
    );
}





function onik_images_register_ob_start()
{
    // Only start output buffering if not running tests
    if (defined('ONIK_IMAGES_TESTS')) {
        return;
    }
    if (is_admin() || (defined('DOING_AJAX') && DOING_AJAX)) {
        return;
    }

    if (wp_doing_ajax() || wp_is_json_request()) {
        return;
    }
    if (onik_images_get_current_request_path() && strpos(onik_images_get_current_request_path(), '/wp-json/') !== false) {
        return;
    }
    // if is activated, return
    if (get_option('onik_lens_activated') !== '1') {
        return;
    }
    ob_start('alter_html');
}
