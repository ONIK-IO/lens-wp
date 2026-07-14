=== ONIK Lens ===
Contributors: onik
Tags: images, cdn, webp, avif, youtube, performance, lazy-load, core-web-vitals
Requires at least: 6.0
Tested up to: 6.4
Requires PHP: 8.1
Stable tag: 0.15.260714.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Optimize every image and YouTube embed on your site through the ONIK Lens CDN. Modern formats, responsive sizes, edge delivery. Originals stay untouched.

== Description ==

ONIK Lens optimizes every image and YouTube embed on your site and serves them through the [ONIK Lens CDN](https://onik.io/wp/lens). Modern formats (AVIF/WebP), tailored sizes (mobile and desktop), and delivery from an edge node closest to the visitor.

Slow images are the primary reason WordPress sites fail Core Web Vitals like Largest Contentful Paint (LCP). Many image optimization techniques permanently convert your media. ONIK Lens takes a different approach: your original files stay completely untouched. Optimization happens on our edge nodes, saving your server's CPU, disk space, and bandwidth.

**Key features:**

* **Instant results** — activate and your images are immediately served in the right format, at the right size, and from the edge.
* **Modern formats** — AVIF, WebP, JPEG. Automatically uses the most efficient format supported by the visitor's browser.
* **Lightweight and fast** — no image processing on your server, no bloated libraries, no background jobs.
* **Non-destructive** — your WordPress Media Library is never modified; uninstall by simply deactivating the plugin.
* **Works everywhere** — optimizes images in media, theme assets, sliders, page builders (Elementor, Divi, Beaver Builder, and more), and any HTML on the page.
* **Full control of lazy load and sizing** — configure lazy loading per CSS selector, control how many images load eagerly above the fold, and fine-tune responsive srcset breakpoints — all from the settings panel.
* **YouTube facade** — replaces YouTube embeds with a lightweight screenshot placeholder. The player only loads on click, eliminating the ~500 KB embed penalty on first load.
* **Site preloads for LCP** — inject `<link rel="preload">` hints for your most critical above-the-fold assets to directly improve LCP.

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/onik-images/`, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the **Plugins** screen.
3. Go to **Settings → ONIK Lens**.
4. On first visit you'll see an activation panel that lists exactly what data will be sent to `app.onik.io`. Click **Activate ONIK Lens** to consent and complete activation.
5. Once activated, your images are served via the ONIK Lens CDN. Default settings work great out of the box; tailor them on the settings page if needed.

You need an [ONIK Lens account](https://onik.io/wp/lens) to use this plugin. A free trial is available.

== External services ==

This plugin connects to two external services to function. Both are operated by ONIK.

**1. ONIK Lens activation service — `https://app.onik.io/api/lens/activate`**

* **When it's called:** only when you click the **Activate ONIK Lens** button on the settings page, and periodically afterward (every 24 hours on success, every 1 hour on failure) to re-verify your account is still active.
* **What is sent:** the WordPress site URL, site name, WordPress admin email address, your configured ONIK tenant + site slug, and the plugin version. The request is HTTPS-only.
* **Why it's sent:** to verify that your site is associated with an active ONIK Lens account before image optimization is enabled.
* **What is stored:** ONIK stores the data above against your account record for billing and support purposes. See [ONIK Privacy Policy](https://onik.io/wp/lens) for details.
* **How to stop:** deactivating the plugin halts all further calls. No data is sent before you click Activate.

**2. ONIK Lens image CDN — `https://images.onik.io/`**

* **When it's called:** every time a page on your site is rendered, after activation. Your visitor's browser fetches optimized image variants directly from this CDN.
* **What is sent:** the public URL of each image to be optimized (appended to the CDN path), plus standard HTTP request metadata from the visitor's browser (User-Agent, Accept headers, IP).
* **Why it's sent:** to serve format-converted, resized, edge-cached images.
* **How to stop:** deactivating the plugin returns all `<img src>` URLs to their original values on the next page render.

By using this plugin you also agree to the [ONIK Lens Terms of Service](https://onik.io/wp/lens) and [Privacy Policy](https://onik.io/wp/lens).

== Privacy ==

ONIK Lens does not set any cookies, does not track site visitors directly, and does not collect any data from the WordPress admin user beyond what is listed in the **External services** section above. All transfers to ONIK servers are over HTTPS.

The activation HTTP POST is only sent when the WordPress administrator clicks the **Activate ONIK Lens** button on the plugin settings page. The data shown in the consent panel at that time is the entire data set sent — there are no hidden fields.

GDPR-aware site operators should disclose the use of the ONIK Lens CDN in their own site privacy notice if their visitors are in jurisdictions that require it, since image requests will route through `images.onik.io` once activated.

== Frequently Asked Questions ==

= Does this plugin modify my original images? =

No. Your WordPress Media Library and uploaded files are never touched. Optimization happens at the CDN edge based on the public URL of each image.

= What happens if I deactivate the plugin? =

The plugin stops rewriting HTML output on the next page render — `<img src>` URLs return to their originals. No data is sent to ONIK after deactivation.

= Does the plugin work without an ONIK account? =

No. Image rewriting is gated on successful activation against your ONIK Lens account. Without activation, the plugin is inert.

= Where is the activation API hosted? =

`app.onik.io`. See the **External services** section for full details on what's sent and when.

= Does this plugin collect any data from site visitors? =

No data is collected by the plugin from visitors. Once activated, visitor browsers fetch optimized images directly from `images.onik.io`; ONIK receives the standard HTTP metadata that any CDN does (User-Agent, IP, etc.) for the purpose of serving the image.

== Changelog ==

= 0.15.260714.1 =
* Initial WordPress.org release.
* Refactored into namespaced modules.
* Tightened settings sanitization (tab whitelist, URL scheme check, text-field sanitizers).
* Activation API call now requires explicit user consent via the Activate button on the settings page.

== Upgrade Notice ==

= 0.15.260714.1 =
First public release.
