# Progressive Image Bundle

[![Build Status](https://img.shields.io/github/actions/workflow/status/tito10047/progressive-image-bundle/symfony.yml?branch=main)](https://github.com/tito10047/progressive-image-bundle/actions)
[![Latest Stable Version](https://img.shields.io/packagist/v/tito10047/progressive-image-bundle.svg)](https://packagist.org/packages/tito10047/progressive-image-bundle)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D%208.2-8892bf.svg)](https://php.net)
[![PHP Version](https://img.shields.io/badge/Symfony-%3E%3D%206.4-black?logo=symfony)](https://symfony.com/)
[![Coverage Status](https://coveralls.io/repos/github/tito10047/progressive-image-bundle/badge.svg?branch=main)](https://coveralls.io/github/tito10047/progressive-image-bundle?branch=main)


### High-performance progressive image loading for Symfony.
Deliver lightning-fast user experiences by serving beautiful Blurhash placeholders while high-resolution images load in the background, eliminating layout shifts and boosting SEO.

```twig
<twig:pgi:Image src="images/hero.jpg" alt="Amazing Landscape" >Image Not Found</twig:pgi:Image>
```

![Progressive Image Preview](docs/preview.gif)

## 🚀 Key Features

-   **Zero CLS (Cumulative Layout Shift):** Automatically extracts and injects image dimensions to reserve space, ensuring a stable layout during loading.
-   **Smart Metadata Extraction:** Uses PHP Streams to read only the necessary bytes for dimensions and hashes—no more loading 20MB images into RAM.
-   **Modern Frontend Stack:** Built on **Symfony UX Twig Components** and **Stimulus** for a seamless, reactive developer experience.
-   **Cloud-Ready Architecture:** Flexible `LoaderInterface` supports local files, network drives, and S3 (via custom loaders).
-   **Advanced Path Resolution:** Resolve images via Filesystem, AssetMapper, or a custom Chain resolver.
-   **Smart Preload Injection:** Automatically injects `<link rel="preload">` tags or HTTP headers for hero images, boosting LCP scores by eliminating "indirect discovery".
-   **Developer Experience (DX):** Simple `<twig:pgi:Image>` component with full support for custom attributes, filters, and decorators (e.g., LiipImagine).

## 📦 Installation

Install the bundle via Composer:

```console
composer require tito10047/progressive-image-bundle
```

If you are not using Symfony Flex, register the bundle manually:

```php
// config/bundles.php
return [
    // ...
    Tito10047\ProgressiveImageBundle\ProgressiveImageBundle::class => ['all' => true],
];
```

## ⚙️ Configuration
This configuration is optional,
create `config/packages/progressive_image.yaml` to configure your resolvers and loaders.

```yaml
progressive_image:
    # Define how to locate your images
    resolvers:
        public_files:
            type: "filesystem"
            roots: ['%kernel.project_dir%/public']
            allowUnresolvable: true
            
        assets:
            type: "asset_mapper"
            
        # Try multiple resolvers in order
        chain:
            type: "chain"
            resolvers:
                - 'public_files'
                - 'assets'

    # Global settings
    driver: "gd"          # Image processor: "gd" or "imagick"
    loader: "progressive_image.filesystem.loader" # Service ID for loading file streams
    resolver: "chain"     # Default resolver to use
    cache: "app.cache.progressive_image" # Recommended to use a persistent cache

    # Resolution for the generated Blurhash
    hash_resolution:
        width: 10
        height: 8

    # Integrations
    path_decorators:
        - "progressive_image.decorator.liip_imagine" # Enable LiipImagine support

when@dev:
    progressive_image:
        resolver: chain
```

## 🎨 Usage

Simply use the provided Twig component in your templates. The component automatically handles the placeholder generation and Stimulus controller initialization.

```twig
{# Simple usage #}
<twig:pgi:Image src="images/hero.jpg" alt="Amazing Landscape" />

{# Optimize LCP by preloading the image #}
<twig:pgi:Image src="images/hero.jpg" preload />

{# With custom attributes and LiipImagine filter #}
<twig:pgi:Image 
    :context="{ 'filter': 'my_liip_filter' }"
    src="uploads/portrait.png" 
    alt="User Profile"
    class="rounded-full shadow-lg"
    style="border: 2px solid #fff;"
/>
```

## ⚡ Smart Preload Injection (LCP Optimization)

One of the biggest challenges for Core Web Vitals (LCP) is the "Indirect Discovery" of images. If your hero image is hidden behind a component or managed by JavaScript, the browser's preload scanner won't find it fast enough.

This bundle solves this by implementing a **Dependency Discovery Pattern**:
1. **Collection:** While Twig renders your components, the bundle automatically collects the URLs of images marked with the `preload` attribute.
2. **Injection:** A Kernel Response Listener intercepts the final response and injects `<link rel="preload">` tags directly into the HTML `<head>` (or as HTTP Link headers) before it's sent to the user.

**Key Benefits:**
- **Zero-Config:** Just add the `preload` attribute, and the bundle handles the complex logic of moving links to the head.
- **Native Performance:** Supports both HTML injection and HTTP/2 Link Headers for even faster delivery.

## 🏗️ Architecture

### Stream-based Approach
Unlike traditional tools that load the entire image into memory to get dimensions, this bundle uses **PHP Streams**. By interacting with the `LoaderInterface`, we can peek at the image headers to extract metadata (Width, Height, MIME type, and Blurhash). 

This is particularly critical when handling:
- **Large Files:** Avoid `Memory Limit Exceeded` errors on 50MB+ source files.
- **S3 / Network Drives:** Only download the minimal required bytes over the wire instead of the full file, significantly reducing latency and bandwidth costs.

### Extensibility
- **Loaders:** Implement `LoaderInterface` to fetch images from any source (GCS, Azure Blob, External APIs).
- **Resolvers:** Implement `PathResolverInterface` to customize how logical paths are mapped to physical locations.
- **Decorators:** Modify the final image URL (e.g., adding CDN prefixes or image manipulation parameters).

## 📄 License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
