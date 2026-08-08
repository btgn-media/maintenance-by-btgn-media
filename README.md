# Maintenance by #btgn.media

A WordPress plugin for a maintenance or coming-soon page with a per-project **session
bypass** via URL parameter and cookie. Show a custom HTML/CSS page or any existing
page you've built — works with any page builder or theme.

By **[btgn.media](https://btgn.media)** · `#btgn.media`

## Features

- **Selectable type:** Maintenance (HTTP 503) or Coming Soon (HTTP 200).
- **Session bypass:** A freely definable URL parameter (per project). Visiting
  `https://example.com/?your-parameter` sets a signed session cookie — that visitor
  sees the full website until the browser is closed. Perfect for sharing a preview
  link with a client, no login required.
- **Two display modes:**
  1. Custom HTML & CSS (loads the site's styles, so utility frameworks like ACSS work).
  2. Any existing page — built with any builder (Etch, Bricks, Elementor, Gutenberg …)
     — with optional switches to hide the theme header and/or footer.
- **SEO-safe:** `noindex,nofollow` for visitors without the bypass.
- **Bilingual UI:** English (base) and German (including formal/AT/CH variants).
- **Self-updating:** Pulls new versions straight from GitHub Releases.

## Installation

1. Download the latest zip from the [Releases page](https://github.com/btgn-media/maintenance-by-btgn-media/releases).
2. WordPress → Plugins → Add New → Upload Plugin → activate.
3. Settings → **Maintenance by #btgn.media**.

> When upgrading from an older version that used a different folder name, deactivate
> and delete the old version first. Your settings are preserved.

## Auto-updates

The plugin checks GitHub Releases and offers updates through the normal WordPress
plugin-update flow. New versions are delivered automatically.

## License

GPL-2.0-or-later
