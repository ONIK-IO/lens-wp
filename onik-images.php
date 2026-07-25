<?php
/*
 * Plugin Name:       ONIK Lens
 * Plugin URI:        https://onik.io/wp/lens
 * Description:       ONIK Lens automatically optimizes images and YouTube videos. See Settings -> ONIK Lens for configuration.
 * Version:           0.17.260725
 * Author:            ONIK
 * Author URI:        https://onik.io/
 * Requires at least: 6.0
 * Tested up to:      6.4
 * Requires PHP:      8.1
 */

define('ONIK_IMAGES_VERSION', '0.17.260725');

require_once __DIR__ . '/vendor/autoload.php';

require_once __DIR__ . '/src/Sanitization/sanitizers.php';
require_once __DIR__ . '/src/Support/support.php';
require_once __DIR__ . '/src/Frontend/frontend-gates.php';

require_once __DIR__ . '/src/Rewrite/rewriter.php';
require_once __DIR__ . '/src/Rewrite/divi-multiview.php';
require_once __DIR__ . '/src/Rewrite/Collectors/image-collector.php';
require_once __DIR__ . '/src/Rewrite/Collectors/source-collector.php';
require_once __DIR__ . '/src/Rewrite/Collectors/div-collector.php';
require_once __DIR__ . '/src/Rewrite/Collectors/inline-style-collector.php';
require_once __DIR__ . '/src/Rewrite/Collectors/regex-collector.php';
require_once __DIR__ . '/src/Rewrite/Collectors/youtube-collector.php';

\OnikImages\Plugin::boot(__FILE__);
