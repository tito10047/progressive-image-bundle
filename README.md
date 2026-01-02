# Progressive Image Bundle

[![Build Status](https://img.shields.io/github/actions/workflow/status/tito10047/progressive-image-bundle/symfony.yml?branch=main)](https://github.com/tito10047/progressive-image-bundle/actions)
[![Latest Stable Version](https://img.shields.io/packagist/v/tito10047/progressive-image-bundle.svg)](https://packagist.org/packages/tito10047/progressive-image-bundle)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D%208.2-8892bf.svg)](https://php.net)
[![PHP Version](https://img.shields.io/badge/Symfony-%3E%3D%206.4-black?logo=symfony)](https://symfony.com/)
[![Coverage Status](https://coveralls.io/repos/github/tito10047/progressive-image-bundle/badge.svg?branch=main)](https://coveralls.io/github/tito10047/progressive-image-bundle?branch=main)


### High-performance progressive image loading for Symfony.
Deliver lightning-fast user experiences by serving beautiful Blurhash placeholders while high-resolution images load in the background. This bundle simplifies responsive images with a **Breakpoint-First approach** (supporting standard aliases like `sm`, `md`, `lg`, `xl`) and features seamless **LiipImagine integration** for automatic thumbnail generation. It eliminates layout shifts (Zero CLS), boosts SEO with smart preload injection, and ensures upscale protection—all while maintaining a minimal memory footprint through stream-based metadata extraction.

```twig
<twig:pgi:Image src="images/hero.jpg" alt="Amazing Landscape" grid="md-6@landscape xl-12@portrait">Image Not Found</twig:pgi:Image>
```

![Progressive Image Preview](docs/preview.gif)

## 🚀 Key Features

### Core Features
-   **Smart Responsive Strategy:** Breakpoint-First approach with built-in **Upscale Protection**. Never serve blurry upscaled images again.
-   **Smart Preload Injection:** Automatically injects `<link rel="preload">` tags or HTTP headers for hero images, boosting LCP scores by eliminating "indirect discovery".
-   **Zero CLS (Cumulative Layout Shift):** Automatically extracts and injects image dimensions to reserve space, ensuring a stable layout during loading.

### Other Features
-   **Smart Metadata Extraction:** Uses PHP Streams to read only the necessary bytes for dimensions and hashes—no more loading 20MB images into RAM.
-   **Modern Frontend Stack:** Built on **Symfony UX Twig Components** and **Stimulus** for a seamless, reactive developer experience.
-   **Cloud-Ready Architecture:** Flexible `LoaderInterface` supports local files, network drives, and S3 (via custom loaders).
-   **Advanced Path Resolution:** Resolve images via Filesystem, AssetMapper, or a custom Chain resolver.
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

## 🚀 Smart Responsive Strategy
Stop managing magic pixel numbers. This bundle introduces a Breakpoint-First approach with built-in Upscale Protection.

### 1. Define your Grid
Configure your project's grid in a central configuration. You can use built-in frameworks like `bootstrap` or `tailwind`, or define your own.

```yaml
# config/packages/progressive_image.yaml
progressive_image:
    responsive_strategy:
        grid:
            framework: bootstrap # automatically sets 12 columns and standard breakpoints
            # OR custom:
            # framework: custom
            # columns: 12
            # layouts:
            #     xl: { min_viewport: 1200, max_container: 1140 }
            #     md: { min_viewport: 768, max_container: 720 }
            #     sm: { min_viewport: 0, max_container: null } # null means fluid (100vw)
```

### 2. Simple Grid-based Usage
In your Twig templates, use the `grid` attribute to define how many columns the image should occupy at each breakpoint.

```twig
{# Image takes 12 columns on mobile (fluid) and 4 columns on desktop #}
<twig:pgi:Image src="hero.jpg" grid="sm-12 xl-4" />

{# You can also specify aspect ratios per breakpoint #}
<twig:pgi:Image src="hero.jpg" grid="sm-12@1-1 xl-4@16-9" />
```

The bundle automatically:
1. Calculates the exact pixel width based on your grid configuration.
2. Generates the `srcset` with optimized image sizes.
3. Generates the `sizes` attribute (e.g., `(min-width: 1200px) 380px, 100vw`).
4. **Protects against upscaling**: If the original image is smaller than the calculated size, it won't generate a blurry version.

### 3. Aspect Ratios
You can define global aspect ratios or use them directly in the `grid` parameter.

```yaml
progressive_image:
    responsive_strategy:
        ratios:
            landscape: "16/9"
            portrait: "3/4"
            square: "1/1"
```

In Twig:
```twig
{# Use global ratio #}
<twig:pgi:Image src="hero.jpg" grid="md-6" ratio="landscape" />

{# Or override per breakpoint #}
<twig:pgi:Image src="hero.jpg" grid="sm-12@square md-6@landscape" />
```

### 4. Intelligence: Built-in Upscale Protection

The bundle never generates an image larger than the original source. For example, if your grid calculates a required width of 1200px but the original image is only 1000px:
- The bundle filters out any variants larger than 1000px.
- It ensures the browser never tries to download a 1200px upscaled (and thus blurry) version.

**Result:** No blurry upscaled images, saved CPU cycles, and reduced storage waste.

### LiipImagine Integration

If you use [LiipImagineBundle](https://github.com/liip/LiipImagineBundle), this bundle integrates seamlessly to generate thumbnails on-the-fly.

By default, the bundle uses `LiipImagineResponsiveImageUrlGenerator` when LiipImagine is detected, which generates dynamic thumbnails based on the required dimensions.

```yaml
# config/packages/progressive_image.yaml
progressive_image:
    # This is often automatically configured if LiipImagine is present
    # path_decorators:
    #     - "progressive_image.decorator.liip_imagine"
```

When using `grid`, the bundle will automatically request the correct sizes from LiipImagine, using the original image's aspect ratio or the one specified in the `grid` parameter. It uses a signed URL to safely generate any required thumbnail size.


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
