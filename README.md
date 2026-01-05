# <img src="docs/logo/SVG/ImageBundleLogo-01-cropped.svg" width="100" align="center" alt="Logo"> Progressive Image Bundle

[![Build Status](https://img.shields.io/packagist/v/tito10047/progressive-image-bundle?label=PGI&color=7747d6&logo=data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABQAAAAUCAYAAACNiR0NAAAAxHpUWHRSYXcgcHJvZmlsZSB0eXBlIGV4aWYAAHjabVBbDsQgCPz3FHsEBbRwHPtK9gZ7/AWhTdt0EseBMYCk7ffd08cAhRLViZu0lhUkJNBVcHb0wSXTYA8ovHLPp9MATaHe6CG3eH/ky1nAr66qXgrxEsZ8NyQaAD8KRSO0iUDFGoUkCiG4cUzY/Vu5CU/XL8xbvoP9JKN9AbFcnd17xjTp9taqfRBgw4JZGbH5AGinJewqyuAKpnBoY0KJSXQhb3s6kP5+41pRc5WxuAAAAYVpQ0NQSUNDIHByb2ZpbGUAAHicfZG9S8NQFMVPU0tFKg4WFHHIUJ3soiKOtQpFqBBqhVYdTF76BU0akhQXR8G14ODHYtXBxVlXB1dBEPwA8Q8QJ0UXKfG+pNAixguP9+O8ew7v3QcIzSrTrJ4EoOm2mUklxVx+VQy/IoAQIghiSGaWMSdJafjW1z11U93FeZZ/35/VrxYsBgRE4gQzTJt4g3hm0zY47xNHWVlWic+JJ0y6IPEj1xWP3ziXXBZ4ZtTMZuaJo8RiqYuVLmZlUyOeJo6pmk75Qs5jlfMWZ61aZ+178hdGCvrKMtdpjSKFRSxBgggFdVRQhY047TopFjJ0nvTxj7h+iVwKuSpg5FhADRpk1w/+B79naxWnJr2kSBIIvTjOxxgQ3gVaDcf5Pnac1gkQfAau9I6/1gRmP0lvdLTYETCwDVxcdzRlD7jcAYafDNmUXSlISygWgfcz+qY8MHgL9K15c2uf4/QByNKs0jfAwSEwXqLsdZ9393bP7d+e9vx+ABCRcn82wOgGAAANeGlUWHRYTUw6Y29tLmFkb2JlLnhtcAAAAAAAPD94cGFja2V0IGJlZ2luPSLvu78iIGlkPSJXNU0wTXBDZWhpSHpyZVN6TlRjemtjOWQiPz4KPHg6eG1wbWV0YSB4bWxuczp4PSJhZG9iZTpuczptZXRhLyIgeDp4bXB0az0iWE1QIENvcmUgNC40LjAtRXhpdjIiPgogPHJkZjpSREYgeG1sbnM6cmRmPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5LzAyLzIyLXJkZi1zeW50YXgtbnMjIj4KICA8cmRmOkRlc2NyaXB0aW9uIHJkZjphYm91dD0iIgogICAgeG1sbnM6eG1wTU09Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC9tbS8iCiAgICB4bWxuczpzdEV2dD0iaHR0cDovL25zLmFkb2JlLmNvbS94YXAvMS4wL3NUeXBlL1Jlc291cmNlRXZlbnQjIgogICAgeG1sbnM6ZGM9Imh0dHA6Ly9wdXJsLm9yZy9kYy9lbGVtZW50cy8xLjEvIgogICAgeG1sbnM6R0lNUD0iaHR0cDovL3d3dy5naW1wLm9yZy94bXAvIgogICAgeG1sbnM6dGlmZj0iaHR0cDovL25zLmFkb2JlLmNvbS90aWZmLzEuMC8iCiAgICB4bWxuczp4bXA9Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC8iCiAgIHhtcE1NOkRvY3VtZW50SUQ9ImdpbXA6ZG9jaWQ6Z2ltcDoyMDM5MWRjMi1jYzc4LTRlNmMtODBjZi1iZjk3MjBiZGNmZjEiCiAgIHhtcE1NOkluc3RhbmNlSUQ9InhtcC5paWQ6ZjgzZjI0YTktMjQ0YS00OGMwLTg4OGItOTM1NjRiMjRjMWY2IgogICB4bXBNTTpPcmlnaW5hbERvY3VtZW50SUQ9InhtcC5kaWQ6ZjcwN2I4YjEtZmM1Ni00N2JlLWEyYWItYmEyMjNmZmMzNTBjIgogICBkYzpGb3JtYXQ9ImltYWdlL3BuZyIKICAgR0lNUDpBUEk9IjIuMCIKICAgR0lNUDpQbGF0Zm9ybT0iTGludXgiCiAgIEdJTVA6VGltZVN0YW1wPSIxNzY3NjE0NjMxMDA5MDc2IgogICBHSU1QOlZlcnNpb249IjIuMTAuMzYiCiAgIHRpZmY6T3JpZW50YXRpb249IjEiCiAgIHhtcDpDcmVhdG9yVG9vbD0iR0lNUCAyLjEwIgogICB4bXA6TWV0YWRhdGFEYXRlPSIyMDI2OjAxOjA1VDEzOjAzOjQ5KzAxOjAwIgogICB4bXA6TW9kaWZ5RGF0ZT0iMjAyNjowMTowNVQxMzowMzo0OSswMTowMCI+CiAgIDx4bXBNTTpIaXN0b3J5PgogICAgPHJkZjpTZXE+CiAgICAgPHJkZjpsaQogICAgICBzdEV2dDphY3Rpb249InNhdmVkIgogICAgICBzdEV2dDpjaGFuZ2VkPSIvIgogICAgICBzdEV2dDppbnN0YW5jZUlEPSJ4bXAuaWlkOmQ3NzEzMWZmLTI4YzUtNDFmZC04OGMxLTNjZDU4MmRkY2QxOSIKICAgICAgc3RFdnQ6c29mdHdhcmVBZ2VudD0iR2ltcCAyLjEwIChMaW51eCkiCiAgICAgIHN0RXZ0OndoZW49IjIwMjYtMDEtMDVUMTM6MDM6NTErMDE6MDAiLz4KICAgIDwvcmRmOlNlcT4KICAgPC94bXBNTTpIaXN0b3J5PgogIDwvcmRmOkRlc2NyaXB0aW9uPgogPC9yZGY6UkRGPgo8L3g6eG1wbWV0YT4KICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIAogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgCiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAKICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIAogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgCiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAKICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIAogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgCiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAKICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIAogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgCiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAKICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIAogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgCiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAKICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIAogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgCiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAKICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIAogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgCiAgICAgICAgICAgICAgICAgICAgICAgICAgIAo8P3hwYWNrZXQgZW5kPSJ3Ij8+VyePlgAAAAZiS0dEAC8AdQC1U+PuEgAAAAlwSFlzAAAuIwAALiMBeKU/dgAAAAd0SU1FB+oBBQwDM+xSgHcAAAGXSURBVDjLrZQxS1xBFIW/fWyRWBwRXUylKQKyuCC4KdKYJoVF0uQ/2FhaJI36G9IGKyG2QiBYKFhEIU2yWijpN00QdiWexUAIuCkyC8PuPN8TPNWbN3fOnHvPnVux/QY4pBwmJB3afg20E/svqsCFpJMybLab4fNH6oztRsbd0BmczQuo3qLkCTCe2APo2q5J+lJIaPslcCapVZD+rO1lSfvx/2woaBLoAeu2v9mu235nuz5MKKkN9Hu9XiXENYHpYYWPgXOgDjSBMeCtpL85Qrv9fn8xyqaVquG1pOeDtIBnwHEOYQbcBHVTQC3Lqc8gzSmgFtXsKFyC7RlgOyrBPpAlXZa0Fi1bgeQzMAt8sL0CHIT1iMsPbL8CLoE/KTcjMoAl4DTUd7QPJW0lXkIe2QBjuY1texX4Gbr/qgTZragGVz9GJHO2f92BrB3HZYlmXQD2SpLtSOoUveVdYDFl0BCOgI3Ctyzpt+33wE4JhUu2W4XT5j+vvpacjzO5w+E+ULG9CXyPpO3aflrSlEfAJ6ABPATm/wHisbydiUBJHAAAAABJRU5ErkJggg==)](https://github.com/tito10047/progressive-image-bundle)
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

Simply use the Twig component. The bundle takes care of the rest.

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

The bundle supports flexible size assignment based on breakpoints you know from Tailwind or Bootstrap:

| Selector              | Meaning                                          |
|:----------------------|:-------------------------------------------------|
| `6`                   | 6 grid columns on all breakpoints                |
| `md:6`                | 6 grid columns from `md` breakpoint              |
| `lg:4@landscape`      | 4 columns from `lg` with 16:9 aspect ratio       |
| `xs:12@square`        | 12 columns on `xs` with 1:1 aspect ratio         |
| `xxl:[430x370]`       | Explicit dimensions for a specific breakpoint    |
| `xl:[100%]@landscape` | 100% container width with landscape aspect ratio |

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
