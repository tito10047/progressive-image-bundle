# Configuration Reference

Every key lives under the `progressive_image:` root in
`config/packages/progressive_image.yaml`. This page mirrors
`Tito10047\ProgressiveImageBundle\DependencyInjection\Configuration` exactly — if a key
isn't listed here, it doesn't exist.

## Resolvers

`resolvers` maps a name to a strategy for turning a logical path (the `src` you pass to
`<twig:pgi:Image>`) into a real, readable source. At least one is required if you want the
bundle to read image bytes/dimensions itself.

```yaml
progressive_image:
    resolvers:
        default:
            type: filesystem
            roots: ['%kernel.project_dir%/public']
            allowUnresolvable: true
        my_asset_mapper:
            type: asset_mapper
        chain_resolver:
            type: chain
            resolvers: ['default', 'my_asset_mapper']
    resolver: default # which entry above is "the" default resolver
```

| Key                         | Type                | Default | Notes |
|:-----------------------------|:--------------------|:--------|:------|
| `resolvers.<name>.type`      | `filesystem\|asset_mapper\|chain` | *required* | |
| `resolvers.<name>.roots`     | `string[]`           | — | Required for `filesystem`; searched in order. |
| `resolvers.<name>.allowUnresolvable` | `bool`       | `false` | If `true`, an unresolvable path doesn't throw. |
| `resolvers.<name>.resolvers` | `string[]`           | — | Required for `chain`; names of other `resolvers` entries, tried in order. |
| `resolver`                   | `string`             | `default` | Which `resolvers` entry is aliased to `progressive_image.resolver.default`. If exactly one resolver is configured, it's used automatically even if not named `default`. With two or more and none named `default`, the container fails to compile — name one `default` or set this explicitly. |

## Image analysis

| Key                    | Type            | Default    | Notes |
|:------------------------|:-----------------|:-----------|:------|
| `driver`                 | `gd\|imagick`    | `gd`       | Selects `progressive_image.analyzer.{gd,imagick}` and (if the Variant pipeline is enabled) the Intervention Image driver. |
| `loader`                 | `string`\|null   | `null`     | Service id; falls back to `progressive_image.filesystem.loader`. |
| `cache`                  | `string`\|null   | `null`     | Service id for the *metadata* cache (dimensions/Blurhash), falls back to `cache.app`. |
| `image_cache_service`    | `string`         | `cache.app`| Service id used when `image_cache_enabled: true` — must resolve to a `TagAwareCacheInterface` (checked at compile time). |
| `hash_resolution.width`  | `int`            | `10`       | Blurhash sample grid width. |
| `hash_resolution.height` | `int`            | `8`        | Blurhash sample grid height. |
| `fallback_image`         | `string`\|null   | `null`     | Path used when metadata resolution fails entirely. |
| `image_cache_enabled`    | `bool`           | `false`    | Fragment-caches the rendered `<twig:pgi:Image>` HTML — see [Caching](/guide/caching). |
| `ttl`                    | `int`\|null      | `null`     | TTL (seconds) for both the metadata cache and the fragment cache. |

## Retina

| Key                       | Type    | Default   |
|:---------------------------|:--------|:----------|
| `retina.enabled`           | `bool`  | `true`    |
| `retina.multipliers`       | `int[]` | `[1, 2]`  |

## Responsive strategy

| Key                                        | Type                                   | Default   | Notes |
|:--------------------------------------------|:----------------------------------------|:----------|:------|
| `responsive_strategy.generator`             | `string`\|null                          | `null`    | Service id of a `ResponsiveImageUrlGeneratorInterface`. Overrides *everything else*, including the Variant pipeline — see [Custom Responsive URL Generator](/cookbook/custom-responsive-url-generator). |
| `responsive_strategy.fluid_max_width`       | `int`                                   | `1920`    | Assumed max viewport width (px) for estimating vw-based breakpoint pixel widths. |
| `responsive_strategy.grid.framework`        | `bootstrap\|tailwind\|custom`           | `custom`  | Picking `bootstrap`/`tailwind` seeds `columns`/`gutter`/`layouts` with that framework's real breakpoints — explicit values you also set win over the preset. |
| `responsive_strategy.grid.columns`          | `int`                                   | `12`      | |
| `responsive_strategy.grid.gutter`           | `int`                                   | `24`      | |
| `responsive_strategy.grid.layouts.<name>.min_viewport` | `int`                        | —         | Viewport width (px) at which this breakpoint activates. |
| `responsive_strategy.grid.layouts.<name>.max_container` | `int`\|null                 | `null`    | `null` means "100vw" (fluid) instead of a fixed container width. |
| `responsive_strategy.ratios.<name>`         | `string` (e.g. `"16/9"`)                | —         | Named aspect ratios usable as `@name` in the `sizes` attribute. |

See [Responsive Grid & Ratios](/guide/responsive-grid-and-ratios) for the full picture,
including the built-in Bootstrap/Tailwind breakpoint tables.

## Path decoration & per-request config

| Key                 | Type       | Default | Notes |
|:---------------------|:-----------|:--------|:------|
| `path_decorators`    | `string[]` | `[]`    | Service ids implementing `PathDecoratorInterface`, applied in order — see [Custom Path Decorator](/cookbook/custom-path-decorator). |
| `image_configs`      | `array`    | `[]`    | Arbitrary filter/format/quality config merged into every Variant spec — see [Filters, Formats & Quality](/guide/variant-pipeline/filters-formats-and-quality). |
| `secret`             | `string`\|null | `null` | HMAC key for `VariantId` hashing; falls back to `%kernel.secret%`. |

## Variant store

Only relevant once you set `variant_store.storage` — see
[Variant Pipeline → Storage](/guide/variant-pipeline/storage) for the full explanation.

| Key                                | Type      | Default        | Notes |
|:-------------------------------------|:----------|:---------------|:------|
| `variant_store.storage`             | `string`\|null | `null`    | Service id of a `League\Flysystem\FilesystemOperator`. **Leaving this unset keeps the bundle in "legacy" mode — no variants are ever generated**, only responsive attributes/URLs are computed. |
| `variant_store.prefix`              | `string`  | `''`           | Path prefix inside the Flysystem filesystem. |
| `variant_store.public_url_prefix`   | `string`  | `/media/pgi`   | URL prefix used to build each variant's public URL. |
| `variant_store.fail_marker_ttl`     | `int`     | `300`          | Seconds a failed generation is remembered, to throttle repeated attempts against a broken source. |

## Generation

| Key                                   | Type                          | Default        | Notes |
|:----------------------------------------|:-------------------------------|:---------------|:------|
| `generation.strategy`                   | `async\|sync\|terminate`       | `async`        | See [Generation Strategies](/guide/variant-pipeline/generation-strategies). |
| `generation.transport`                  | `string`                       | `async_images` | Messenger transport name; only used when `strategy: async`. The bundle wires `framework.messenger.routing[GenerateVariant] = <this>` for you. |
| `generation.fallback_while_pending`     | `original\|wait`               | `original`      | What URL to serve while a variant is still generating. |
| `generation.lock_store`                 | `string`\|null (Symfony Lock DSN) | `null`      | Falls back to a `FlockStore` in `%kernel.cache_dir%/pgi-locks`. |

## Formats & quality

| Key                            | Type                                  | Default                                    |
|:---------------------------------|:----------------------------------------|:--------------------------------------------|
| `formats.default`                | `jpeg\|png\|webp\|avif`                  | `jpeg`                                      |
| `formats.default_quality`        | `int`                                    | `85`                                        |
| `formats.negotiate`              | list of `jpeg\|png\|webp\|avif`          | `[]`                                        |
| `formats.quality.jpeg`           | `int`                                    | `85`                                        |
| `formats.quality.webp`           | `int`                                    | `82`                                        |
| `formats.quality.avif`           | `int`                                    | `60`                                        |
| `formats.quality.png`            | `int`                                    | `90`                                        |

## Filter sets

```yaml
progressive_image:
    filter_sets:
        thumbnail_square:
            filters:
                thumbnail: { size: [400, 400], mode: outbound }
            format: webp
            quality: 80
```

`filter_sets.<name>` is a free-form array (`filters`, `format`, `quality`) — see
[Filters, Formats & Quality](/guide/variant-pipeline/filters-formats-and-quality) for the
full filter list and merge rules. A typo here fails at compile time
(`cache:clear`/`cache:warmup`), not at request time.

## Post-processors

Optional CLI re-encode/optimize step run after the image is generated. Each requires the
named binary to be installed and resolvable on `$PATH` (or a full path) — checked at
compile time.

| Key                              | Type     | Default        |
|:-----------------------------------|:---------|:---------------|
| `post_processors.jpegoptim.enabled`| `bool`   | `false`        |
| `post_processors.jpegoptim.bin`    | `string` | `jpegoptim`    |
| `post_processors.pngquant.enabled` | `bool`   | `false`        |
| `post_processors.pngquant.bin`     | `string` | `pngquant`     |
| `post_processors.cwebp.enabled`    | `bool`   | `false`        |
| `post_processors.cwebp.bin`        | `string` | `cwebp`        |
| `post_processors.avifenc.enabled`  | `bool`   | `false`        |
| `post_processors.avifenc.bin`      | `string` | `avifenc`      |
