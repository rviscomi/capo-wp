# Capo WordPress Plugin (`capo-wp`)

[![License: GPL v2+](https://img.shields.io/badge/License-GPL%20v2%2B-blue.svg)](LICENSE)
[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4.svg)](https://php.net)

Automatically reorders the `<head>` of your WordPress pages for optimal browser rendering and web performance using the [Capo.js](https://rviscomi.github.io/capo.js/) methodology.

---

## 🚀 Why Capo for WordPress?

In standard WordPress setups, WordPress Core, block themes, SEO plugins (like Yoast SEO), analytics scripts (like Site Kit/GTM), and third-party plugins all inject tags into `wp_head()` at arbitrary priorities.

This leads to common performance pitfalls:
* ⚠️ **Late Page Titles:** Heavy SEO meta tags or inline JSON-LD output *before* `<title>`.
* ⚠️ **Delayed Network Connections:** Async analytics scripts and preconnects pushed *after* dozens of stylesheet and style blocks.
* ⚠️ **Render Blocking:** Preloads and font styles buried beneath third-party scripts.

**Capo solves this automatically** by intercepting the page response via output buffering and stably sorting all `<head>` elements according to their impact on the browser's critical rendering path.

---

## 📊 The Capo Head Priority Spectrum

Capo groups elements into 11 strictly evaluated priority weights (from 10 down to 0):

| Weight | Visual | Category | Elements & Detection |
| :---: | :---: | :--- | :--- |
| **10** | 🟪 | **Critical Meta & Viewport** | `<base>`, `<meta charset>`, `<meta name="viewport">`, critical `http-equiv` headers (CSP, origin-trial, accept-ch) |
| **9** | 🟥 | **Title** | `<title>` |
| **8** | 🟧 | **Preconnect** | `<link rel="preconnect">` |
| **7** | 🟧 | **Async Script** | `<script src="..." async>` |
| **6** | 🟨 | **CSS @import** | `<style>` blocks containing `@import` rules |
| **5** | 🟨 | **Sync Scripts** | Synchronous/inline JavaScript (`<script>` without defer/async) |
| **4** | 🟩 | **Stylesheets & Styles** | `<link rel="stylesheet">`, `<style>` blocks |
| **3** | 🟩 | **Preload** | `<link rel="preload">`, `<link rel="modulepreload">` |
| **2** | 🟦 | **Defer Script** | `<script src="..." defer>`, `<script src="..." type="module">` |
| **1** | 🟫 | **Prefetch / Prerender** | `<link rel="prefetch">`, `<link rel="dns-prefetch">`, `<link rel="prerender">` |
| **0** | ⬜ | **Other Metadata** | OpenGraph, Twitter cards, Schema JSON-LD, RSS feeds, favicons, generator tags |

> [!NOTE]
> **Deterministic Stable Sort:** Elements with equal priority weight retain their exact sequential order, preserving CSS cascade specificity and JavaScript dependency execution.

---

## 🛠️ Features

* **Zero Configuration:** Activate and your `<head>` is immediately optimized.
* **Cache Compatible:** Integrates seamlessly with page caching solutions like **WP Super Cache**, **W3 Total Cache**, **WP Rocket**, and **LiteSpeed Cache** (static cached HTML stores the optimized `<head>` with 0ms runtime overhead).
* **Safe HTML Tokenizer:** Respects HTML comments, conditional comments (`<!--[if ...]>`), CDATA blocks, and inline script markup.
* **Site Health Integration:** Adds a diagnostic check under **Tools > Site Health** to verify optimization status.
* **Testing & Bypass Mode:** Append `?capo=off` to any frontend URL to inspect the raw un-reordered head.

---

## 📦 Installation

### From WordPress Admin
1. Go to **Plugins > Add New**.
2. Upload the `capo.zip` archive or search for **Capo**.
3. Click **Install Now** and **Activate**.

### Manual Installation
1. Clone this repository into your plugins directory:
   ```bash
   git clone https://github.com/rviscomi/capo-wp.git /var/www/html/wp-content/plugins/capo
   ```
2. Activate via WP-CLI:
   ```bash
   wp plugin activate capo
   ```
3. If using a page cache plugin, flush your cache:
   ```bash
   wp super-cache flush
   ```

---

## 🧪 Testing Parity

Capo WP includes automated test suites ensuring 1:1 parity with the upstream `capo.js` rule definitions:

```bash
php tests/test-rules.php
php tests/test-parser.php
```

---

## 📄 License

Capo for WordPress is open-source software licensed under the [GPL-2.0-or-later](LICENSE).
