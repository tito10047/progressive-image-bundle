# <img src="logo/SVG/ImageBundleLogo-01-cropped.svg" width="100" align="center" alt="Logo"> Advanced Features

## Smart Preload Injection (LCP Optimization)

The biggest challenge for Largest Contentful Paint (LCP) is the late discovery of the image by the browser. If your main image (hero image) is deep within a component,
the browser discovers it too late.

Progressive Image Bundle solves this using the **Dependency Discovery Pattern**:

1. During Twig component rendering, the bundle collects URLs of images that have the `preload` attribute.
2. A Kernel Response Listener then automatically injects `<link rel="preload">` into the HTML header (`<head>`) or sends it as an HTTP Link Header.

### Usage:

Simply add the `preload` attribute to the component:

```twig
<twig:pgi:Image src="hero.jpg" preload />
```

---

## Transparent HTML Caching

Generating Blurhash and reading metadata requires CPU power. On pages with dozens of images, this can add up.

The bundle offers transparent caching of the resulting HTML:

1. If caching is enabled, the bundle checks the cache before rendering.
2. If an entry is found, it returns the ready-made HTML and skips all PHP logic.
3. The cache key is generated automatically from all component attributes.

### Configuration:

```yaml
progressive_image:
    image_cache_enabled: true
    image_cache_service: 'cache.app'
    ttl: 86400
```

---

## Stream-based Metadata Extraction

Unlike other tools, this bundle **does not load the entire image into RAM** just to determine its dimensions.

It utilizes PHP Streams and reads only the necessary bytes from the file header. This is critical for:

- **Large files:** Prevents `Memory Limit Exceeded` errors.
- **Network drives / S3:** Downloads only a fraction of the data needed for analysis, saving bandwidth and reducing latency.

---

## Automatic Generation and Loader

The bundle automatically generates all required image sizes (thumbnails) when they are first requested. If you use the LiipImagine decorator, generation is delegated to
LiipImagine.

Thanks to `LoaderInterface`, the bundle can work with files on:

- **Local disk.**
- **Network disk (NAS, NFS).**
- **Cloud storage (S3, Azure)** - requires custom loader implementation.

---

## Architecture and Extensibility

The bundle is designed so you can replace any of its parts:

- **LoaderInterface:** Implement to load images from your own sources (e.g., external API, Azure Blob).
- **PathResolverInterface:** Customize how logical paths map to physical files.
- **Decorators:** Modify the final URL address (e.g., adding a CDN prefix).
