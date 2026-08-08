=== Maintenance by #btgn.media ===
Contributors: btgnmedia
Tags: maintenance, coming soon, maintenance mode, preview, bypass
Requires at least: 6.5
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 2.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Maintenance or coming-soon page with a per-project session bypass via URL parameter and cookie. Works with any builder or theme.

== Description ==

Maintenance by #btgn.media shows a maintenance or coming-soon page to visitors while letting you (and anyone with a project-specific bypass link) browse the full site.

* **Selectable type:** Maintenance (HTTP 503) or Coming Soon (HTTP 200).
* **Session bypass:** Define your own URL parameter per project. Visiting `https://example.com/?your-parameter` sets a signed session cookie — that visitor sees the full website until the browser is closed. Great for sharing a preview link with a client, no login required.
* **Two display modes:**
    1. Custom HTML & CSS — the page loads the site's styles, so utility frameworks like Automatic.css (ACSS) work directly.
    2. Any existing page — built with any builder (Etch, Bricks, Elementor, Gutenberg …) — with optional switches to hide the theme header and/or footer.
* **SEO-safe:** Visitors without the bypass receive `noindex,nofollow`.
* **Bilingual UI:** English and German (including formal/AT/CH variants).
* **Self-updating:** New versions are delivered through GitHub Releases via the normal WordPress update flow.

Logged-in editors and administrators, as well as the login screen, admin, AJAX and cron, are never blocked.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/maintenance-by-btgn-media`, or install the zip via Plugins → Add New → Upload Plugin.
2. Activate the plugin.
3. Go to Settings → Maintenance by #btgn.media and configure type, bypass parameter and appearance.

== Frequently Asked Questions ==

= Does the bypass persist? =

The bypass uses a session cookie. It lasts until the visitor closes their browser; afterwards the maintenance page is shown again. Changing the bypass parameter invalidates any bypass cookies already handed out.

= Which page builders are supported? =

Any of them. The "Existing page" mode renders the selected page through the normal WordPress pipeline, so whatever builder or theme produced it — Etch, Bricks, Elementor, Gutenberg — renders exactly as usual, including its styles.

= Can I hide the theme header and footer on the selected page? =

Yes. Two switches let you hide the header and/or footer via CSS. The defaults target the semantic `<header>`/`<footer>` elements; custom selectors are available under "Advanced" if your markup differs.

