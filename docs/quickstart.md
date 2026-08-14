# <img src="logo/SVG/ImageBundleLogo-01-cropped.svg" width="100" align="center" alt="Logo"> Quick Start

Get your first responsive image working in under 5 minutes.

## 1. Install the bundle

```bash
composer require tito10047/progressive-image-bundle
```

## 2. Register the bundle

If Symfony Flex did not register it automatically, add it to `config/bundles.php`:

```php
// config/bundles.php
return [
    // ...
    Tito10047\ProgressiveImageBundle\ProgressiveImageBundle::class => ['all' => true],
];
```

## 3. Add routing

The bundle exposes an endpoint used to serve dynamically processed images. Add it to your routing:

```yaml
# config/routes/progressive_image.yaml
progressive_image:
    resource: "@ProgressiveImageBundle/config/routes.php"
```

## 4. Configure a tagged cache pool

The bundle uses a cache pool with tagging support to invalidate generated image HTML. Add a pool to your framework configuration:

```yaml
# config/packages/framework.yaml
framework:
    cache:
        pools:
            cache.my_cache:
                tags: true
```

## 5. Configure the bundle

Create `config/packages/progressive_image.yaml` and point the bundle at the cache pool you just created. The example below uses Tailwind breakpoints, standard aspect ratios, and retina (1x + 2x) support — adjust to match your project:

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
    image_cache_service: 'cache.my_cache'
    retina:
        enabled: true
        multipliers: [1, 2]
    resolvers:
        my_path_resolver:
            type: "filesystem"
            roots: [ '%kernel.project_dir%/public' ]
            allowUnresolvable: true
        my_asset_mapper_resolver:
            type: "asset_mapper"
        chain_resolver:
            type: "chain"
            resolvers:
                - 'my_path_resolver'
                - 'my_asset_mapper_resolver'
when@dev:
    progressive_image:
        resolver: chain_resolver
```

## 6. Generate the responsive CSS

The bundle communicates image widths to your CSS via custom properties. Run the generator once (and re-run whenever you change breakpoints):

```bash
php bin/console progressive-image:generate-custom-css
```

This creates `assets/styles/progressive-image-custom.css`. Import it in your `assets/app.js` (or equivalent entry point) so the responsive sizing and aspect-ratio locking work correctly in the browser:

```js
// assets/app.js
import './styles/progressive-image-custom.css';
```

> **Tailwind / Bootstrap users:** pre-built CSS files are already included — import the one that matches your framework instead of running the generator:
> ```js
> import './styles/progressive-image-tailwind.css';
> // or
> import './styles/progressive-image-bootstrap.css';
> ```

## 7. Use the Twig component

Place an image in any Twig template. The bundle generates all required sizes automatically on first request:

```twig
<twig:pgi:Image 
    src="images/hero.jpg" 
    sizes="sm:12 md:6@landscape lg:4@square"
    alt="Responsive hero image" 
/>
```

| `sizes` token    | What it means                                              |
|:-----------------|:-----------------------------------------------------------|
| `sm:12`          | Full-width (12 columns) from the `sm` breakpoint           |
| `md:6@landscape` | Half-width (6 columns) from `md`, cropped to 16:9          |
| `lg:4@square`    | One-third width (4 columns) from `lg`, cropped to 1:1      |

That's it. On first load the bundle resizes the source image for every breakpoint, generates a Blurhash placeholder, and renders a `<picture>` element with the correct `srcset`. Subsequent requests are served from cache.

---

## What's next?

- [Installation](installation.md) — full install options and Symfony Flex notes
- [Configuration](configuration.md) — all available settings explained
- [Usage](usage.md) — every `<twig:pgi:Image>` attribute documented
- [Responsive Strategy](responsive-strategy.md) — how grid columns and breakpoints are resolved
- [Advanced Features](advanced.md) — LCP preload, Point of Interest cropping, LiipImagine integration
