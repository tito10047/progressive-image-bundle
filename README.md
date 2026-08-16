# <img src="docs/logo/SVG/ImageBundleLogo-01-cropped.svg" width="100" align="center" alt="Logo"> Progressive Image Bundle

[![Build Status](https://img.shields.io/github/actions/workflow/status/tito10047/progressive-image-bundle/ci.yml?branch=main)](https://github.com/tito10047/progressive-image-bundle/actions)
[![PHP-CS-Fixer](https://img.shields.io/github/actions/workflow/status/tito10047/progressive-image-bundle/ci.yml?branch=main&label=code%20style)](https://github.com/tito10047/progressive-image-bundle/actions/workflows/ci.yml)
[![PHPStan](https://img.shields.io/github/actions/workflow/status/tito10047/progressive-image-bundle/ci.yml?branch=main&label=phpstan)](https://github.com/tito10047/progressive-image-bundle/actions/workflows/ci.yml)
[![Latest Stable Version](https://img.shields.io/packagist/v/tito10047/progressive-image-bundle.svg)](https://packagist.org/packages/tito10047/progressive-image-bundle)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D%208.2-8892bf.svg)](https://php.net)
[![Symfony Version](https://img.shields.io/badge/Symfony-%3E%3D%206.4-black?logo=symfony)](https://symfony.com/)
[![Symfony Style](https://img.shields.io/badge/code%20style-symfony-black?logo=symfony)](https://symfony.com/)
[![Coverage Status](https://coveralls.io/repos/github/tito10047/progressive-image-bundle/badge.svg?branch=main)](https://coveralls.io/github/tito10047/progressive-image-bundle?branch=main)

### High-performance, Fully Responsive Images for Symfony.

This bundle handles everything you need for modern image management. From **fully responsive images** with Tailwind-like selectors, to **blur placeholders**, to **content-addressed generation of every required size**, on local disk or any cloud storage Flysystem supports.

> 🌍 **Production Ready:** This bundle is successfully deployed and running in production on live websites such as [mostka.sk](https://mostka.sk/) and [vsetkosada.sk](https://vsetkosada.sk/).

> ⚠️ **Version 2.0 is experimental.** It's a from-scratch DDD rewrite of the Variant pipeline and hasn't been tagged as a stable release yet — expect breaking changes before a final `2.0.0` tag.

**📖 Full documentation: [tito10047.github.io/progressive-image-bundle](https://tito10047.github.io/progressive-image-bundle/)**

---

## ✨ Key Features

- 🎨 **Blur & Error Placeholders:** Users see a beautiful Blurhash placeholder while loading. If an image is not found, the bundle automatically displays a stylish error
  placeholder.
- 🖼️ **Responsive via `<picture>`:** Uses the modern `<picture>` element with multiple `<source>` tags for optimal browser selection and performance.
- 📱 **Tailwind-like Selectors:** Define responsiveness naturally directly in your template using familiar breakpoints.
- 🖼️ **Retina Support:** Automatically generates 1x, 2x (and more) versions for high-density displays.
- ⚙️ **Content-Addressed Variant Pipeline:** Every generated size is identified by a deterministic hash of its source + spec — no coordination needed across workers, and generation runs synchronously, on `kernel.terminate`, or async via Messenger, your choice.
- 🎯 **Zero CLS (Cumulative Layout Shift):** Automatically reserves space for the image, preventing content jumping during load.
- ⚡ **Smart Preload:** Automatically injects `<link rel="preload">` for critical images (hero images), significantly improving LCP scores.
- 🧩 **Built to Be Extended:** Storage backend, image engine, post-processors, path resolution and URL generation are all swappable Symfony services.

---

## 🎨 Usage

Simply use the Twig component. The bundle takes care of everything — it automatically calculates the required image dimensions for each breakpoint, resizes the original,
and generates all necessary variants on the fly.

```twig
{# Basic usage - everything is automatic #}
<twig:pgi:Image src="{{ asset(images/hero.jpg) }}" alt="Beautiful landscape" />

{# With Tailwind-like selectors for perfect responsiveness #}
<twig:pgi:Image 
    src="{{ asset(images/hero.jpg) }}" 
    sizes="sm:12 md:6@landscape lg:4@square"
    alt="Responsive image" 
/>
```

### 📱 Selector Examples (Breakpoint Assignment)

The bundle supports flexible size assignment based on breakpoints you know from Tailwind or Bootstrap. For each selector, it automatically calculates the final pixel
dimensions based on the container width defined by your CSS framework (Bootstrap or Tailwind) and generates the corresponding image.

| Selector              | Meaning                                          | Resulting behavior                                      |
|:-----------------------|:---------------------------------------------------|:------------------------------------------------------------|
| `6`                    | 6 grid columns on all breakpoints                  | Width: 50% of container, original aspect ratio               |
| `md:6`                 | 6 grid columns from `md` breakpoint                | From `md` up: 50% of container, below `md`: full width       |
| `lg:4@landscape`       | 4 columns from `lg` with 16:9 aspect ratio         | From `lg` up: 33.3% of container, cropped to 16:9 ratio       |
| `xs:12@square`         | 12 columns on `xs` with 1:1 aspect ratio           | Full width container, cropped to 1:1 square                   |
| `xxl:[430x370]`        | Explicit dimensions for a specific breakpoint      | Exact size 430x370px on `xxl` and larger                      |
| `xl:[100%]@landscape`  | 100% container width with landscape aspect ratio   | Full width container, cropped to 16:9 ratio                   |
| `lg:4@square\|circle`  | 4 columns on `lg` with a custom modifier           | Applies the `circle` modifier's config (e.g. a filter set)    |

> **What is a "container"?** The bundle automatically detects your CSS framework (Bootstrap or Tailwind) and extracts the exact container widths for each breakpoint from
> its configuration. It then uses these values to calculate the precise pixel dimensions for your images.

Full syntax reference: [The Twig Component → Sizes syntax](https://tito10047.github.io/progressive-image-bundle/guide/twig-component#sizes-syntax).

---

## 🚀 Advanced Features

### Point of Interest (PoI) Cropping

Define a focal point as **pixel coordinates in the original image** (e.g., `pointInterest="544x320"`) so the most important subject stays in frame regardless of the target aspect ratio. The bundle finds the largest region of the original that matches the target ratio, centres it on the focal point, and scales the result — never slicing at original resolution. → [Cookbook: Point of Interest Cropping](https://tito10047.github.io/progressive-image-bundle/cookbook/point-of-interest-cropping)

### Smart Upscaling Protection

The bundle never generates an image larger than the original. If you need 1200px but the original is only 1000px, the bundle uses the original and prevents blurring.

### Stream-based Metadata

To retrieve dimensions and Blurhash, the bundle doesn't load the entire image into RAM (no 20MB files in memory). It uses PHP streams to read only the necessary header
bytes.

### Content-Addressed Variant Generation

Every generated file is identified by an HMAC hash of its source + processing spec — the same request from any request or worker produces the same file, with no
coordination needed beyond a short-lived lock. Choose when generation actually runs: inline in the request (`sync`), deferred to `kernel.terminate`, or asynchronously via
Symfony Messenger (`async`, the default). → [Variant Pipeline → Overview](https://tito10047.github.io/progressive-image-bundle/guide/variant-pipeline/overview)

### Custom Modifiers & Filters

Extend the selector logic with your own modifiers (e.g., `lg:4|circle`), or add your own crop/resize/watermark filters via `filter_sets`. You can implement custom logic
and even prioritize modifiers to override default behavior. → [Cookbook: Custom Modifier](https://tito10047.github.io/progressive-image-bundle/cookbook/custom-modifier)

---

## 📦 Installation

```console
composer require tito10047/progressive-image-bundle
```

See [Getting Started](https://tito10047.github.io/progressive-image-bundle/guide/getting-started) for routing, cache pool, and full setup steps.

---

## ⚙️ Optional Configuration

```yaml
# config/packages/progressive_image.yaml
progressive_image:
    responsive_strategy:
        grid:
            framework: tailwind # or bootstrap
        ratios:
            landscape: "16/9"
            portrait: "3/4"
            square: "1/1"
    image_cache_enabled: true
    retina:
        enabled: true
        multipliers: [1, 2]

    # Opt into the Variant pipeline: generates and stores real resized files.
    # Without this, only responsive attributes/URLs are computed.
    variant_store:
        storage: 'oneup_flysystem.variants_filesystem' # any League\Flysystem\FilesystemOperator
    generation:
        strategy: async # async (default) | sync | terminate
```

Every available option is documented in the [Configuration Reference](https://tito10047.github.io/progressive-image-bundle/guide/configuration-reference).

---

## 📄 License

MIT License. See [LICENSE](LICENSE) for more information.

---

## 📚 Documentation

**📖 Full documentation site: [tito10047.github.io/progressive-image-bundle](https://tito10047.github.io/progressive-image-bundle/)**

| Section | What's in it |
|:--|:--|
| [Guide](https://tito10047.github.io/progressive-image-bundle/guide/getting-started) | Installation, full configuration reference, the Twig component, responsive grid & ratios, caching, architecture |
| [Variant Pipeline](https://tito10047.github.io/progressive-image-bundle/guide/variant-pipeline/overview) | How images actually get generated: content-addressed variants, the three generation strategies, filters/formats/quality, storage |
| [Cookbook](https://tito10047.github.io/progressive-image-bundle/cookbook/custom-storage-backend) | Step-by-step recipes for extending the bundle: custom storage, image engine, post-processors, path decorators, URL generators, modifiers, async workers, point-of-interest cropping, serving behind nginx |
| [API Reference](https://tito10047.github.io/progressive-image-bundle/api) | Every interface, model, route and command, as a quick lookup table |

The docs source lives in [`docs/`](docs/) and is built with [VitePress](https://vitepress.dev/) — run `npm install && npm run dev` inside that directory to preview changes locally.

---

## 📜 Changelog

All notable changes to this project are documented in [CHANGELOG.md](CHANGELOG.md).
