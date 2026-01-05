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

### High-performance, Zero-config, Fully Responsive Images for Symfony.

This bundle handles everything you need for modern image management. From **fully responsive images** with Tailwind-like selectors, to **blur placeholders**, to *
*automatic generation of all required sizes** on local or network storage.

---

## ✨ Key Features

- 🚀 **Zero Configuration:** Install and use. Most features work out of the box.
- 🎨 **Blur & Error Placeholders:** Users see a beautiful Blurhash placeholder while loading. If an image is not found, the bundle automatically displays a stylish error
  placeholder.
- 📱 **Tailwind-like Selectors:** Define responsiveness naturally directly in your template using familiar breakpoints.
- ⚙️ **Automatic Generation:** The bundle automatically generates all necessary image sizes on disk (local or network), saving time and resources.
- 🎯 **Zero CLS (Cumulative Layout Shift):** Automatically reserves space for the image, preventing content jumping during load.
- ⚡ **Smart Preload:** Automatically injects `<link rel="preload">` for critical images (hero images), significantly improving LCP scores.

---

## 🎨 Usage

Simply use the Twig component. The bundle takes care of everything — it automatically calculates the required image dimensions for each breakpoint, resizes the original,
and generates all necessary variants on the fly.

```twig
{# Basic usage - everything is automatic #}
<twig:pgi:Image src="images/hero.jpg" alt="Beautiful landscape" />

{# With Tailwind-like selectors for perfect responsiveness #}
<twig:pgi:Image 
    src="images/hero.jpg" 
    sizes="sm:12 md:6@landscape lg:4@square"
    alt="Responsive image" 
/>
```

### 📱 Selector Examples (Breakpoint Assignment)

The bundle supports flexible size assignment based on breakpoints you know from Tailwind or Bootstrap. For each selector, it automatically calculates the final pixel
dimensions based on the container width defined by your CSS framework (Bootstrap or Tailwind) and generates the corresponding image.

| Selector              | Meaning                                          | Resulting behavior                                      |
|:----------------------|:-------------------------------------------------|:--------------------------------------------------------|
| `6`                   | 6 grid columns on all breakpoints                | Width: 50% of container, original aspect ratio          |
| `md:6`                | 6 grid columns from `md` breakpoint              | From `md` up: 50% of container, below `md`: full width  |
| `lg:4@landscape`      | 4 columns from `lg` with 16:9 aspect ratio       | From `lg` up: 33.3% of container, cropped to 16:9 ratio |
| `xs:12@square`        | 12 columns on `xs` with 1:1 aspect ratio         | Full width container, cropped to 1:1 square             |
| `xxl:[430x370]`       | Explicit dimensions for a specific breakpoint    | Exact size 430x370px on `xxl` and larger                |
| `xl:[100%]@landscape` | 100% container width with landscape aspect ratio | Full width container, cropped to 16:9 ratio             |

> **What is a "container"?** The bundle automatically detects your CSS framework (Bootstrap or Tailwind) and extracts the exact container widths for each breakpoint from
> its configuration. It then uses these values to calculate the precise pixel dimensions for your images.

---

## 🚀 Advanced Features

### Point of Interest (PoI) Cropping

Define a focal point (e.g., `75x25`) so the most important object remains in frame regardless of the crop.

### Smart Upscaling Protection

The bundle never generates an image larger than the original. If you need 1200px but the original is only 1000px, the bundle uses the original and prevents blurring.

### Stream-based Metadata

To retrieve dimensions and Blurhash, the bundle doesn't load the entire image into RAM (no 20MB files in memory). It uses PHP streams to read only the necessary header
bytes.

---

## 📦 Installation

```console
composer require tito10047/progressive-image-bundle
```

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
```

---

## 📄 License

MIT License. See [LICENSE](LICENSE) for more information.

---

## 📚 Documentation

For detailed guides, configuration, and advanced features, check out our full documentation:

- [**Introduction**](docs/index.md)
- [**Installation**](docs/installation.md)
- [**Configuration**](docs/configuration.md)
- [**Usage (Twig component)**](docs/usage.md)
- [**Responsive Strategy**](docs/responsive-strategy.md)
- [**Advanced Features**](docs/advanced.md)
