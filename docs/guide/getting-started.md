# Getting Started

Progressive Image Bundle requires **PHP 8.3+** and **Symfony 6.4, 7.4, or 8.1+**. It ships
as a Symfony bundle (`tito10047/progressive-image-bundle`) and works with either
[Symfony UX AssetMapper](https://symfony.com/bundles/AssetMapperBundle) or a Webpack
Encore / bundler-based frontend, since it only needs a working
[Stimulus](https://stimulus.hotwired.dev/) environment.

> **Why PHP 8.3?** The Variant pipeline is built on
> [`intervention/image`](https://packagist.org/packages/intervention/image) `^4.2`, which
> itself requires PHP 8.3+. See [`composer.json`](https://github.com/tito10047/progressive-image-bundle/blob/main/composer.json)
> for the exact per-package Symfony version constraints.

## 1. Install via Composer

```bash
composer require tito10047/progressive-image-bundle
```

That's it. The bundle ships a [Symfony Flex recipe](https://github.com/symfony/recipes-contrib),
so on a Flex-enabled project (any app created via `composer create-project symfony/skeleton`
or `symfony new`) `composer require` already registers the bundle, imports its routing, and
drops a starter `config/packages/progressive_image.yaml` — including an AssetMapper-aware
resolver chain if `symfony/asset-mapper` is installed. Skip to
[step 3](#3-configure-a-tagged-cache-pool-optional-but-recommended).

## 2. Not using Flex? Register and import manually

```php
// config/bundles.php
return [
    // ...
    Tito10047\ProgressiveImageBundle\ProgressiveImageBundle::class => ['all' => true],
];
```

The bundle exposes two routes: `pgi_variant_serve`, used to serve a variant that is still
pending generation and as an nginx `try_files` fallback target (see
[Serving Behind Nginx](/cookbook/serving-behind-nginx)); and `pgi_variant_resolve`, the
on-the-fly `{filterSet}/{path}` resolve route (see
[On-the-Fly Resolve Route](/cookbook/on-the-fly-resolve-route)). Import both even if you
don't plan to use those flows immediately:

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

> **Installed via Flex?** Step 1 already dropped a working
> `config/packages/progressive_image.yaml` for you — this section is for reviewing or
> adjusting it (e.g. picking a different `variant_store.storage`), not writing it from
> scratch.

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

> **Installed via Flex?** The recipe already ran this for you once (via a
> `composer-scripts` hook) and imported the result into `assets/app.js`. You only need to
> re-run it yourself after changing `responsive_strategy`.

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
