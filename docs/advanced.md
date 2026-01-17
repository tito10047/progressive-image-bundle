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
- **Modifiers:** Extend selectors with custom logic (e.g. `lg:4|circle`).

---

## Modifiers

Modifiers allow you to extend the selector format with custom logic. This is useful when you need to pass additional parameters to your image processing pipeline (e.g.,
LiipImagine filters or custom URL parameters).

### Format

```text
breakpoint:columns@ratio|modifier1|modifier2
```

Example: `lg:4@landscape|circle|border-5`

### Custom Modifier Implementation

To create a custom modifier, implement `Tito10047\ProgressiveImageBundle\Modifier\ModifierInterface` and register it as a service. If you use Symfony autoconfiguration,
it will be automatically tagged with `progressive_image.modifier`.

```php
namespace App\Modifier;

use Tito10047\ProgressiveImageBundle\Modifier\ModifierInterface;

class CircleModifier implements ModifierInterface
{
    public function supports(string $modifier): bool
    {
        return $modifier === 'circle';
    }

    public function modify(string $modifier, array $context): array
    {
        // This will be passed to the URL generator context
        $context['filter'] = 'circle_crop';
        
        return $context;
    }
}
```

The resulting `context` is passed to the `ResponsiveImageUrlGeneratorInterface::generateUrl` method, allowing you to influence the final URL.

### Filter Modifiers

The bundle includes a specialized system for image filters. You can implement `Tito10047\ProgressiveImageBundle\Modifier\FilterModifierInterface` to handle specific
filter names (like `circle`, `grayscale`, or custom ones like `border-5`).

Filter modifiers support **Prioritization**. If you want to override a built-in filter, simply register your modifier with a higher priority (default is 0, built-in
filters use -100).

```php
namespace App\Modifier;

use Tito10047\ProgressiveImageBundle\Modifier\FilterModifierInterface;

class CustomCircleModifier implements FilterModifierInterface
{
    public function supports(string $filterName): bool
    {
        return $filterName === 'circle';
    }

    public function modify(string $filterName, array $currentOptions): array
    {
        // Override default circle behavior
        $currentOptions['filter'] = 'my_custom_circle_filter';
        $currentOptions['radius'] = 50;
        
        return $currentOptions;
    }
}
```

If a modifier is not recognized by any `FilterModifierInterface`, it is added to the `custom_filters` array in the context.
