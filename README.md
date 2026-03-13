# ONIK Lens — WordPress Plugin

Optimizes images and YouTube embeds via the [ONIK Lens](https://onik.io/lens) CDN for faster page loads and better Core Web Vitals.

## Features

- **Image optimization** — rewrites `<img>` tags to serve WebP/AVIF via ONIK Lens with responsive `srcset`
- **YouTube optimization** — replaces standard iframes with lightweight `<lite-youtube>` elements (loads 224× faster, privacy-friendly)
- **Lazy loading** — configurable per selector, with control over how many images load eagerly
- **Preloads** — inject `<link rel="preload">` tags with optional URL filtering

## Requirements

- WordPress 6.0+
- PHP 8.0+
- An [ONIK Lens](https://onik.io/lens) account

## Installation

### Via Composer

```bash
composer require onik/onik-images
```

### Manual

1. Download the plugin source code zip from the latest release. [Latest Release](https://github.com/ONIK-IO/lens-wp/releases/tag/v0.10)
2. Upload the folder to `/wp-content/plugins/onik-lens/`, or add a plugin from a zip file in the WordPress Admin panel.
3. Activate via **Plugins** in the WordPress admin
4. Configure under **Settings > ONIK Lens**


## Configuration

Settings are stored as JSON in the WordPress admin under **Settings > ONIK Lens**.


## License

GPL-2.0-or-later — see [LICENSE](LICENSE)
