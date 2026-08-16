# Getting Started

Progressive Image Bundle requires **PHP 8.2+** and **Symfony 6.4+**. It ships as a
Symfony bundle (`tito10047/progressive-image-bundle`) and works with either
[Symfony UX AssetMapper](https://symfony.com/bundles/AssetMapperBundle) or a Webpack
Encore / bundler-based frontend, since it only needs a working
[Stimulus](https://stimulus.hotwired.dev/) environment.

## 1. Install via Composer

```bash
composer require tito10047/progressive-image-bundle
```

If you're not using Symfony Flex, register the bundle manually:

```php
// config/bundles.php
return [
    // ...
    Tito10047\ProgressiveImageBundle\ProgressiveImageBundle::class => ['all' => true],
];
```

## 2. Import the routing

The bundle exposes one route, `pgi_variant_serve`, used to serve a variant that is still
pending generation and as an nginx `try_files` fallback target (see
[Serving Behind Nginx](/cookbook/serving-behind-nginx)). Import it even if you don't plan
to use those flows immediately:

```yaml
# config/routes/progressive_image.yaml
progressive_image:
    resource: "@ProgressiveImageBundle/config/routes.php"
```

## 3. Configure a tagged cache pool (optional but recommended)

If you enable `image_cache_enabled` (fragment-caches the rendered `<twig:pgi:Image>` HTML,
see [Caching](/guide/caching)), the bundle needs a cache pool that supports tag-based
invalidation:

```yaml
# config/packages/framework.yaml
framework:
    cache:
        pools:
            cache.my_cache:
                tags: true
```

## 4. Configure the bundle

At minimum, decide **how images get resolved from a Twig `src` string to a real file**
(a `resolvers` entry) and, if you want the bundle to actually generate resized files
instead of just calculating dimensions, **where generated variants are stored**
(`variant_store.storage`, see [Variant Pipeline → Storage](/guide/variant-pipeline/storage)).

```yaml
# config/packages/progressive_image.yaml
progressive_image:
    resolvers:
        default:
            type: filesystem
            roots: ['%kernel.project_dir%/public']
            allowUnresolvable: true

    responsive_strategy:
        grid:
            framework: tailwind # or bootstrap, or custom
        ratios:
            landscape: '16/9'
            portrait: '3/4'
            square: '1/1'

    retina:
        enabled: true
        multipliers: [1, 2]

    # Opt into the Variant pipeline: without this, the bundle only computes responsive
    # attributes/URLs — it never generates a resized file itself.
    variant_store:
        storage: 'oneup_flysystem.variants_filesystem' # any League\Flysystem\FilesystemOperator service
```

See the full [Configuration Reference](/guide/configuration-reference) for every option
and its default.

## 5. Generate the responsive CSS

The bundle communicates each breakpoint's target width to the browser via CSS custom
properties. Generate the stylesheet once (re-run whenever you change breakpoints):

```bash
php bin/console progressive-image:generate-custom-css
```

This writes `assets/styles/progressive-image-custom.css`. Import it from your JS entry
point:

```js
// assets/app.js
import './styles/progressive-image-custom.css';
```

> **Using Tailwind or Bootstrap breakpoints?** Pre-built CSS files already ship in the
> bundle's `assets/styles/` directory — import
> `progressive-image-tailwind.css` or `progressive-image-bootstrap.css` instead of
> running the generator.

## 6. Render your first image

```twig
{# Simplest possible usage — one fixed size, no responsive breakpoints #}
<twig:pgi:Image src="{{ asset('images/hero.jpg') }}" alt="Beautiful landscape" />

{# Responsive, with Tailwind-like breakpoint selectors #}
<twig:pgi:Image
    src="{{ asset('images/hero.jpg') }}"
    sizes="sm:12 md:6@landscape lg:4@square"
    alt="Responsive hero image"
/>
```

| `sizes` token    | Meaning                                                    |
|:-----------------|:------------------------------------------------------------|
| `sm:12`          | Full-width (12 columns) from the `sm` breakpoint            |
| `md:6@landscape` | Half-width (6 columns) from `md`, cropped to 16:9           |
| `lg:4@square`    | One-third width (4 columns) from `lg`, cropped to 1:1       |

The bundle resolves the source's real dimensions, computes the pixel width needed at each
breakpoint, and (if `variant_store.storage` is configured) generates every required size on
first request — a `<picture>` element with the correct `srcset`/`sizes` is rendered
immediately, and subsequent requests are served straight from storage. See
[The Twig Component](/guide/twig-component) for every available attribute.

## What's next?

- [Configuration Reference](/guide/configuration-reference) — every setting, explained
- [The Twig Component](/guide/twig-component) — full `sizes` syntax, point of interest, retina, preload
- [Variant Pipeline → Overview](/guide/variant-pipeline/overview) — how images actually get generated
- [Architecture](/guide/architecture) — how the codebase is layered, for contributors
