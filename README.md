# ONIK Lens - The easiest way to optimize your WordPress images

**Boost your Google PageSpeed score and pass Core Web Vitals — without touching a single file in your Media Library.**

ONIK Lens optimizes every image and YouTube embed on your site and serves them through the [ONIK Lens CDN](https://onik.io/wp/lens). Modern formats (AVIF/WebP), tailored sizes (mobile & desktop), and delivery from an edge node closest to the visitor.

## Why ONIK Lens?

Slow images are the primary reason WordPress sites fail Core Web Vitals like Largest Contentful Paint (LCP). Many image optimization techniques permanently convert your media. ONIK Lens takes a different approach: your original files stay completely untouched. Optimization happens on our edge nodes, saving your server's CPU, disk space, and bandwidth.

## Key Features
- **Instant results** — activate and your images are immediately served in the right format, at the right size (mobile or desktop), and from the edge.
- **Modern Formats** AVIF, WebP, JPEG — Automatically uses the most efficient format supported by your visitor's browser
- **Lightweight & fast** — no image processing on your server, no bloated libraries, no background jobs. All the heavy lifting is offloaded to the edge so your server stays fast and lean.
- **Non-destructive** — your WordPress Media Library is never modified; uninstall by simply deactivating the plugin
- **Works everywhere** — optimizes images in media, theme assets, Sliders, page builders (Elementor, Divi, Beaver Builder, and more), and any HTML on the page
- **Full Control of Lazy Load & Sizing** — Configure lazy loading per CSS selector, control how many images load eagerly above the fold, and fine-tune responsive `srcset` breakpoints to match your design — all from the settings panel.
- **YouTube Facade** — Replaces YouTube embeds with a lightweight screenshot placeholder. The player only loads on click, eliminating the ~500 KB embed penalty on first load — and passing Google Lighthouse's "Facade your YouTube embeds" recommendation.
- **Site Preloads for LCP** — Inject `<link rel="preload">` hints for your most critical above-the-fold assets to directly improve your Largest Contentful Paint (LCP) score.

## Requirements

- WordPress 6.0+
- PHP 8.1+
- An [ONIK Lens](https://onik.io/wp/lens) account ($20 CAD/month, included with ONIK Monitoring, 30-day free trial without account or credit card)

## Installation

### Install in the WordPress Admin

1. [Download the latest release](https://github.com/ONIK-IO/lens-wp/releases/latest/download/onik-lens.zip).
2. In your WordPress admin, go to **Plugins → Add New → Upload Plugin**.
3. Select the downloaded ZIP file and click **Install Now**.
4. Click **Activate Plugin**.

### Install via Composer

If you manage your WordPress installation with Composer (e.g. Bedrock or a custom stack):

```bash
composer require onik/lens-wp
```

The plugin will be installed automatically into your plugins directory via `composer/installers`.

## Connect the Plugin to your ONIK Account

The plugin runs in a full-featured **free trial** the moment it's activated — no account or
credit card required. To keep it running on a production site, connect it to your ONIK
subscription:

1. Log in to [app.onik.io](https://app.onik.io). You will have to create an account and register your site if you have not done so already.
2. Open your site's details.
3. In the left-hand menu, open **Lens Image Optimization**.
4. Copy the site token shown on that page.
5. In WordPress, go to **Settings → ONIK Lens** and find the **Connect to ONIK** panel on the General tab, paste in the token, and click **Connect to ONIK**.

Your tenant, site, and CDN URL are filled in automatically once connected.

## Configuration

Default settings will work great right out of the box.  Tailor the settings in the WordPress admin under **Settings → ONIK Lens**.

No PHP configuration, no `wp-config.php` constants, and no manual file edits are required.

---

## More Information

Full documentation, feature guides, and account setup are available at **[onik.io](https://onik.io/wp/lens)**.

---

## License

GPL-2.0-or-later — see [LICENSE](LICENSE)
