# ONIK Lens — WordPress Plugin

Optimizes images and YouTube embeds via the [ONIK Lens](https://onik.io/lens) CDN for faster page loads and better Core Web Vitals.

## Features

- **Image optimization** — rewrites `<img>` tags to serve WebP/AVIF via ONIK Lens with responsive `srcset`
- **YouTube optimization** — replaces standard iframes with lightweight `<lite-youtube>` elements (loads 224× faster, privacy-friendly)
- **Lazy loading** — configurable per selector, with control over how many images load eagerly
- **Preloads** — inject `<link rel="preload">` tags with optional URL filtering
- **Hybrid DOM processing** — uses DOM only for discovery, applies changes via string replacement to preserve your original HTML exactly

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

1. Upload the plugin folder to `/wp-content/plugins/onik-images/`
2. Activate via **Plugins** in the WordPress admin
3. Configure under **Settings > ONIK Lens**

## Configuration

Settings are stored as JSON in the WordPress admin under **Settings > ONIK Lens**.

### Image Rules

Each key is a CSS selector; the value is a configuration object.

```json
{
    "img.hero": {
        "widths": [800, 1200, 1600],
        "quality": 85,
        "loading": "eager",
        "fetchpriority": "high",
        "decoding": "async",
        "format": "auto",
        "srcSwap": "srcAndSrcSet"
    },
    "img.product-image": {
        "widths": [300, 600, 900],
        "quality": 80,
        "loading": "lazy",
        "lazyLoadAfter": 2,
        "sizes": "(max-width: 768px) 100vw, 50vw",
        "format": "webp",
        "srcSwap": "srcSet"
    }
}
```

| Property | Type | Default | Description |
|---|---|---|---|
| `widths` | integer[] | — | **Required.** Image widths (px) to include in srcset |
| `quality` | integer | `80` | Output quality (1–100) |
| `loading` | string | — | `"lazy"`, `"eager"`, or omit |
| `sizes` | string | — | CSS `sizes` attribute value |
| `lazyLoadAfter` | integer | `0` | Eager-load the first N images, lazy-load the rest |
| `fetchpriority` | string | — | `"high"`, `"low"`, `"auto"`, or omit |
| `decoding` | string | — | `"sync"`, `"async"`, `"auto"`, or omit |
| `format` | string | `"auto"` | `"auto"`, `"webp"`, `"avif"`, `"jpg"`, `"gif"` |
| `srcSwap` | string | `"srcSet"` | `"src"`, `"srcSet"`, or `"srcAndSrcSet"` |

### YouTube Rules

Each key is a CSS selector targeting YouTube iframes.

```json
{
    "iframe[src*='youtube']": {
        "playlabel": "Play video",
        "params": "rel=0&modestbranding=1",
        "js_api": false,
        "style": "width: 100%; border-radius: 8px;"
    }
}
```

| Property | Type | Default | Description |
|---|---|---|---|
| `playlabel` | string | `"Play: {id}"` | Accessible label for the play button |
| `title` | string | — | Video title attribute |
| `params` | string | — | YouTube player parameters |
| `js_api` | boolean | `false` | Enable YouTube IFrame Player API |
| `style` | string | — | Inline CSS appended to the `<lite-youtube>` element |

**Supported YouTube URL formats:** `youtube.com/embed/`, `youtube-nocookie.com/embed/`, `youtube.com/watch?v=`, `youtu.be/`, `youtube.com/v/`

### Preloads

```json
[
    {
        "as": "image",
        "href": "https://example.com/hero.jpg",
        "type": "image/jpeg",
        "fetchpriority": "high",
        "urlFilter": "#/homepage|/about#"
    }
]
```

`urlFilter` is a PHP regex matched against the current page URL — omit it to inject on every page.

## License

GPL-2.0-or-later — see [LICENSE](LICENSE)
