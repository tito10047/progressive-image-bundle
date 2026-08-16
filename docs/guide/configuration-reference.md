# Configuration Reference

Every key lives under the `progressive_image:` root in
`config/packages/progressive_image.yaml`. This page mirrors
`Tito10047\ProgressiveImageBundle\DependencyInjection\Configuration` exactly — if a key
isn't listed here, it doesn't exist.

Every block below is a real, valid, copy-pasteable `progressive_image:` config (hover a
block and click the icon in the top-right corner to copy it) — comments show the type,
default, and any notes for each key, right next to it.

## Resolvers

`resolvers` maps a name to a strategy for turning a logical path (the `src` you pass to
`<twig:pgi:Image>`) into a real, readable source. At least one is required if you want the
bundle to read image bytes/dimensions itself.

```yaml
progressive_image:
    resolvers:
        default:
            type: filesystem # filesystem | asset_mapper | chain — required
            roots: ['%kernel.project_dir%/public'] # string[] — required for filesystem, searched in order
            allowUnresolvable: true # bool, default false — if true, an unresolvable path doesn't throw

        my_asset_mapper:
            type: asset_mapper

        chain_resolver:
            type: chain
            resolvers: ['default', 'my_asset_mapper'] # string[] — required for chain: names of other "resolvers" entries, tried in order

    # Which "resolvers" entry above is aliased to progressive_image.resolver.default.
    # Default: "default". If exactly one resolver is configured, it's used automatically
    # even if not named "default". With two or more and none named "default", the
    # container fails to compile — name one "default" or set this explicitly.
    resolver: default
```

## Image analysis

```yaml
progressive_image:
    driver: gd # gd | imagick, default: gd — also selects the Intervention Image driver when the Variant pipeline is enabled
    loader: null # string|null, default: null — service id; falls back to progressive_image.filesystem.loader
    cache: null # string|null, default: null — service id for the *metadata* cache (dimensions/Blurhash); falls back to cache.app
    image_cache_service: cache.app # string — service id used when image_cache_enabled: true; must resolve to a TagAwareCacheInterface (checked at compile time)
    hash_resolution:
        width: 10 # int, default: 10 — Blurhash sample grid width
        height: 8 # int, default: 8 — Blurhash sample grid height
    fallback_image: null # string|null, default: null — path used when metadata resolution fails entirely
    image_cache_enabled: false # bool, default: false — fragment-caches the rendered <twig:pgi:Image> HTML, see Caching
    ttl: null # int|null, default: null — TTL (seconds) for both the metadata cache and the fragment cache
```

## Retina

```yaml
progressive_image:
    retina:
        enabled: true # bool, default: true
        multipliers: [1, 2] # int[], default: [1, 2]
```

## Responsive strategy

```yaml
progressive_image:
    responsive_strategy:
        # Service id of a ResponsiveImageUrlGeneratorInterface. Overrides *everything
        # else*, including the Variant pipeline — see Cookbook: Custom Responsive URL
        # Generator. string|null, default: null.
        generator: null

        # Assumed max viewport width (px), used to estimate the pixel width of fluid
        # (vw-based) breakpoints. int, default: 1920.
        fluid_max_width: 1920

        grid:
            # bootstrap | tailwind | custom, default: custom. Picking bootstrap/tailwind
            # seeds columns/gutter/layouts below with that framework's real breakpoints —
            # explicit values you also set here win over the preset.
            framework: custom
            columns: 12 # int, default: 12
            gutter: 24 # int, default: 24
            layouts:
                # <name>:
                #     min_viewport: 1024   # int (px) — viewport width at which this breakpoint activates
                #     max_container: 976   # int|null — null means "100vw" (fluid) instead of a fixed container width
                lg: { min_viewport: 1024, max_container: 976 }

        ratios:
            # Named aspect ratios usable as "@name" in the sizes attribute. string, e.g. "16/9".
            landscape: '16/9'
            portrait: '3/4'
            square: '1/1'
```

See [Responsive Grid & Ratios](/guide/responsive-grid-and-ratios) for the full picture,
including the built-in Bootstrap/Tailwind breakpoint tables.

## Path decoration & per-request config

```yaml
progressive_image:
    # Service ids implementing PathDecoratorInterface, applied in order — see Cookbook:
    # Custom Path Decorator. string[], default: [].
    path_decorators: []

    # Arbitrary filter/format/quality config merged into every Variant spec — see
    # Filters, Formats & Quality. array, default: [].
    image_configs: {}

    # HMAC key for VariantId hashing; falls back to %kernel.secret%. string|null, default: null.
    secret: null
```

## Variant store

Only relevant once you set `variant_store.storage` — see
[Variant Pipeline → Storage](/guide/variant-pipeline/storage) for the full explanation.

```yaml
progressive_image:
    variant_store:
        # Service id of a League\Flysystem\FilesystemOperator. string|null, default: null.
        # Leaving this unset keeps the bundle in "legacy" mode — no variants are ever
        # generated, only responsive attributes/URLs are computed.
        storage: null

        prefix: '' # string, default: '' — path prefix inside the Flysystem filesystem
        public_url_prefix: /media/pgi # string, default: /media/pgi — URL prefix used to build each variant's public URL
        fail_marker_ttl: 300 # int (seconds), default: 300 — how long a failed generation is remembered, to throttle repeated attempts against a broken source
```

## Generation

```yaml
progressive_image:
    generation:
        strategy: async # async | sync | terminate, default: async — see Generation Strategies
        transport: async_images # string, default: async_images — Messenger transport name, only used when strategy: async (the bundle wires framework.messenger.routing for you)
        fallback_while_pending: original # original | wait, default: original — what URL to serve while a variant is still generating
        lock_store: null # string|null (Symfony Lock DSN), default: null — falls back to a FlockStore in %kernel.cache_dir%/pgi-locks
```

## Formats & quality

```yaml
progressive_image:
    formats:
        default: jpeg # jpeg | png | webp | avif, default: jpeg
        default_quality: 85 # int, default: 85
        negotiate: [] # list of jpeg | png | webp | avif, default: []
        quality:
            jpeg: 85 # int, default: 85
            webp: 82 # int, default: 82
            avif: 60 # int, default: 60
            png: 90 # int, default: 90
```

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

```yaml
progressive_image:
    post_processors:
        jpegoptim:
            enabled: false # bool, default: false
            bin: jpegoptim # string, default: jpegoptim
        pngquant:
            enabled: false # bool, default: false
            bin: pngquant # string, default: pngquant
        cwebp:
            enabled: false # bool, default: false
            bin: cwebp # string, default: cwebp
        avifenc:
            enabled: false # bool, default: false
            bin: avifenc # string, default: avifenc
```
