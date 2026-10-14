# Nexura Product Video Media Gallery for WooCommerce

<div align="center">
  <a href="https://wordpress.org/plugins/nexura-product-video-media-gallery-for-woocommerce/">
    <img src="https://img.shields.io/badge/WORDPRESS.ORG-DOWNLOAD_FREE-0073aa?logo=wordpress&style=for-the-badge" alt="Download Nexura Product Video Media Gallery for WooCommerce on WordPress.org">
  </a>
</div>

<div align="center">
  <img src="https://img.shields.io/badge/REQUIRES_WP-5.6+-0073aa?labelColor=555555&style=flat-square" alt="Requires WordPress 5.6+">
  <img src="https://img.shields.io/badge/REQUIRES_PHP-7.4+-0073aa?labelColor=555555&style=flat-square" alt="Requires PHP 7.4+">
  <img src="https://img.shields.io/badge/TESTED_UP_TO-7.1-73c713?labelColor=555555&style=flat-square" alt="Tested up to WordPress 7.1">
  <img src="https://img.shields.io/badge/LICENSE-GPLv2-0073aa?labelColor=555555&style=flat-square" alt="License GPLv2 or later">
</div>
<div align="center">
  <a href="https://github.com/nexurasecurity/nexura-product-video-media-gallery-for-woocommerce/actions"><img src="https://img.shields.io/badge/CI-passing-73c713?logo=github&style=flat-square" alt="CI Status"></a>
  <a href="https://github.com/nexurasecurity/nexura-product-video-media-gallery-for-woocommerce/stargazers"><img src="https://img.shields.io/github/stars/nexurasecurity/nexura-product-video-media-gallery-for-woocommerce?style=flat-square&logo=github" alt="GitHub Stars"></a>
  <a href="https://github.com/nexurasecurity/nexura-product-video-media-gallery-for-woocommerce/network/members"><img src="https://img.shields.io/github/forks/nexurasecurity/nexura-product-video-media-gallery-for-woocommerce?style=flat-square&logo=github" alt="GitHub Forks"></a>
  <a href="https://github.com/nexurasecurity/nexura-product-video-media-gallery-for-woocommerce/issues"><img src="https://img.shields.io/github/issues/nexurasecurity/nexura-product-video-media-gallery-for-woocommerce?style=flat-square&color=73c713" alt="GitHub Issues"></a>
</div>

Add responsive YouTube & Vimeo videos to WooCommerce product galleries. Blazing fast, Core Web Vitals optimized, and built to boost store conversions.

---

## 📋 Table of Contents

- [Overview](#overview)
- [Key Features](#key-features)
- [Why Nexura Product Video Gallery?](#why-nexura-product-video-gallery)
- [Free Features](#-free-features)
- [Pro Features](#-pro-features)
- [Universal Theme Compatibility](#-universal-theme-compatibility)
- [Shortcode & Page Builders](#-shortcode--page-builder-integration)
- [Installation](#installation)
- [Frequently Asked Questions](#frequently-asked-questions)
- [Changelog](#changelog)
- [License](#license)

---

## Overview

Did you know that **over 73% of online shoppers** are significantly more likely to make a purchase after watching a real product video? 

Static product images only tell half the story. Shoppers want to see your products in action—how clothes fit in motion, how jewelry sparkles under natural light, how gadgets operate, and what comes inside the unboxing package.

**Nexura Product Video Media Gallery for WooCommerce** is the ultra-lightweight, conversion-focused video gallery engine built specifically to **boost conversions, eliminate buyer hesitation, and slash costly product returns by up to 35%**—all while keeping your website lightning-fast!

---

## ⚡ Why Nexura Product Video Gallery?

The number one fear of every WooCommerce store owner is: *"Will adding videos destroy my mobile Google PageSpeed score and hurt my search rankings?"*

With ordinary video plugins, the answer is sadly yes—they immediately download heavy YouTube and Vimeo `<iframe>` scripts on initial page load, causing mobile PageSpeed scores to tank from 95 down to 40.

**Nexura Product Video Media Gallery solves this completely with our zero-bloat architecture:**

1. **Poster-First Facade Architecture**: Heavy video players are **never** loaded until the visitor actually clicks the play button. Your initial page loads instantly with zero unnecessary HTTP requests.
2. **Instant Largest Contentful Paint (LCP)**: The primary hero product image loads with native `fetchpriority="high"` and `decoding="async"`, ensuring your store passes Google Core Web Vitals with flying colors.
3. **Non-Blocking Deferred Scripts**: All gallery JavaScript is automatically deferred, ensuring zero render-blocking resources.
4. **Smart Mobile Bandwidth Saver**: Inactive slide videos use `preload="none"` to preserve precious mobile cellular data.
5. **Zero External Slider Bloat**: Powered by native, hardware-accelerated JavaScript for butter-smooth 60fps touch scrolling without 200KB slider libraries.
6. **Zero Cumulative Layout Shift (CLS)**: Built-in CSS aspect-ratio containment guarantees zero page jumping or content shifting during load.

---

## 🌟 Free Features

* **Multi-Source Product Video**: Choose any one source via radio — YouTube & Shorts, Vimeo, TikTok, Facebook, custom iframe, or self-hosted MP4/WebM — and add a single product video.
* **Auto-Generated High-Resolution Posters**: Automatically fetches high-definition YouTube cover posters (`hqdefault.jpg`) without requiring manual image uploads.
* **Modern Gallery Presentation Layouts**:
  * *Vertical Left Layout*: Classic luxury e-commerce vertical thumbnail strip aligned to the left of the main stage.
  * *Horizontal Bottom Layout*: Clean, modern thumbnail carousel beneath the product hero display.
* **Aspect Ratio Flexibility**:
  * *Portrait 4:5*: The industry gold standard for fashion, apparel, footwear, and jewelry.
  * *Square 1:1*: Classic, clean format for electronics, accessories, and packaged goods.
* **Smooth Inner Pan-and-Zoom Magnifier**: High-definition pan-and-zoom directly inside the product stage so customers can inspect fine textures, stitching, and material quality.
* **Fullscreen Split Lightbox Modal**: Modern Etsy-style fullscreen modal overlay featuring zoom magnification on one side and thumbnail navigation on the other.
* **WooCommerce Variable Product Video Support**: Attach unique YouTube, Vimeo, or self-hosted videos to individual variations.
* **Smart Audio & Slider Sync**:
  * *Autoplay Pause*: The gallery slider automatically pauses the moment a video plays.
  * *Browser Policy Compliant*: Automatically adapts to Chrome and Safari autoplay policies.
* **Mobile-First Touch Architecture**:
  * Touch swipe gestures with passive event listeners for silky smooth mobile performance.
  * Customizable mobile breakpoint (default 768px).
  * Smart mobile toggle to disable hover-zoom on touchscreens to prevent scrolling lockups.
  * Option to hide arrows and show clean navigation dots on smartphones.
* **Universal Shortcode `[nexura_product_video_gallery]`**: Display the gallery anywhere on custom product landing pages, popups, or custom templates.
* **Page Builder Ready**: Full compatibility with Elementor, Divi Theme Builder, Gutenberg / WooCommerce Blocks (FSE), Bricks Builder, Oxygen, and WPBakery.
* **100% WooCommerce HPOS Compatible**: Fully declared and tested with High-Performance Order Storage (HPOS).

---

## 💎 Pro Features

Unlock maximum sales potential with advanced enterprise capabilities:

* **Shop Archive & Catalog Video Previews**: Engage shoppers directly on Shop, Category, and Tag listing pages:
  * *Hover Autoplay Preview*: Plays muted preview on card hover.
  * *Instant Popup Lightbox*: Opens a quick video modal overlay right from the shop card.
  * *Inline Card Player*: Plays video directly inside the shop product card.
* **Multiple Videos per Product**: Add tutorials, unboxings, demos, and more to a single product.
* **Interactive Catalog Pagination Dots**: Allows shoppers to cycle between multiple product videos directly on category catalog cards.
* **Luxury Gallery Layouts**:
  * *Vertical Right*: Thumbnails aligned on the right.
  * *Mosaic Grid (2-1-2-1)*: High-fashion alternating editorial grid.
  * *2-Column Grid*: Prominent hero image with clean 2-column supporting grid.
  * *1-Column Luxury Stack*: Continuous scroll layout favored by high-end luxury fashion brands.
* **Smartphone Vertical Video (9:16 & 16:9)**: Native vertical video support for TikTok, Instagram Reels, and YouTube Shorts without ugly black sidebars.
* **Advanced Magnifying Lenses**:
  * *Circular & Square Lens Zoom*: Interactive magnifying glass lens following the cursor.
  * *Amazon-Style Side Window Zoom*: High-definition floating inspection window with configurable position and dimensions.
* **Per-Product & Category Style Overrides**: Override layouts, aspect ratios, and magnifier modes individually for specific products or entire categories.
* **Interactive 3D / 360° Product Viewer**: GLB/GLTF models and gallery-image turntables with 2D/3D switcher.

---

## 🎨 Universal Theme Compatibility

Tested and verified with thousands of WooCommerce themes:
* **Storefront** (including sidebar & full-width)
* **Astra** & Astra Pro
* **Flatsome**
* **OceanWP**
* **GeneratePress**
* **Kadence**
* **Hello Elementor**
* **Divi Theme**
* **Twenty Twenty-Four** & all modern Full Site Editing (FSE) block themes

---

## 🧩 Shortcode & Page Builder Integration

Easily display your product video gallery anywhere:

* **Current Product Gallery (Auto-Detect):**
  ```text
  [nexura_product_video_gallery]
  ```

* **Specific Product with Layout Override:**
  ```text
  [nexura_product_video_gallery id="123" layout="horizontal"]
  ```

* **Constrain Width:**
  ```text
  [nexura_product_video_gallery id="123" width="650px"]
  ```

---

## Installation

1. Upload the `nexura-product-video-media-gallery-for-woocommerce` folder to your `/wp-content/plugins/` directory, **or** install it directly via **Plugins → Add New → Upload Plugin**.
2. Activate **Nexura Product Video Media Gallery for WooCommerce** from the WordPress Plugins screen.
3. Ensure **WooCommerce** is active.
4. Navigate to **Products → Video Gallery** in your WordPress admin sidebar to configure settings.
5. Edit any product (**Products → All Products → Edit**) and use the **Nexura Product Video Media Gallery for WooCommerce** metabox to add your videos.

---

## Frequently Asked Questions

<details>
<summary><strong>Where do I find the plugin settings after activating?</strong></summary>

After activation, global settings are located under **Products → Video Gallery** in the WordPress admin sidebar. You can also click **Settings** on the plugin row under **Plugins → Installed Plugins**.
</details>

<details>
<summary><strong>Will this plugin slow down my WooCommerce store?</strong></summary>

No! The plugin utilizes a poster-first facade architecture where heavy iframe players and scripts are only loaded when a customer interacts with the video. The primary product image loads with `fetchpriority="high"` and all gallery JavaScript is deferred.
</details>

<details>
<summary><strong>Does it support YouTube Shorts and Vimeo?</strong></summary>

Yes! Simply paste any YouTube, YouTube Shorts (`shorts/VIDEO_ID`), or Vimeo URL. The plugin automatically fetches posters and renders a responsive player.
</details>

<details>
<summary><strong>Does it support WooCommerce Variable Products?</strong></summary>

Yes! You can assign specific video URLs to individual product variations. When a customer selects an attribute variation (e.g., color or size), the gallery updates automatically.
</details>

<details>
<summary><strong>Does it work with Elementor and Divi?</strong></summary>

Yes! The plugin works natively with Elementor's Product Images widget, Divi Theme Builder, Gutenberg / WooCommerce Blocks (FSE), Bricks Builder, Oxygen, and WPBakery.
</details>

<details>
<summary><strong>Is it compatible with WooCommerce HPOS?</strong></summary>

Yes! Nexura Product Video Media Gallery is 100% compatible and officially declared with WooCommerce High-Performance Order Storage (HPOS).
</details>

---

## Changelog

= 1.0.0 =
* Initial official release for WordPress.org.
* Multi-provider video support (YouTube & Shorts, Vimeo, TikTok, Facebook, custom embed iframe, and self-hosted MP4/WebM).
* VideoObject Schema.org JSON-LD support for Google Search SEO.
* Responsive carousel layouts (Vertical Left, Horizontal Bottom).
* Aspect ratio engine (1:1 Square, 4:5 Portrait).
* Smooth inner pan-and-zoom image magnifier.
* Split fullscreen lightbox modal.
* Dedicated mobile breakpoint and touch swipe controls.
* WooCommerce Variable Product video support.
* Universal Shortcode [nexura_product_video_gallery] and Page Builder support.
* Core Web Vitals and PageSpeed optimizations with poster-first facade architecture.
* WooCommerce HPOS (High-Performance Order Storage) full compatibility.

---

## License

This plugin is licensed under the **GPL v2 or later**.
See the [LICENSE](LICENSE) file for full details, or visit [https://www.gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html).

---

<div align="center">
  Made with ❤️ by <a href="https://github.com/nexurasecurity">Nexura Security</a> &nbsp;·&nbsp;
  <a href="https://wordpress.org/plugins/nexura-product-video-media-gallery-for-woocommerce/">WordPress.org</a> &nbsp;·&nbsp;
  <a href="https://github.com/nexurasecurity/nexura-product-video-media-gallery-for-woocommerce/issues">Report a Bug</a>
</div>
