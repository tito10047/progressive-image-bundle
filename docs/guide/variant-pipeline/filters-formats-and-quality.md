# Filters, Formats & Quality

## How a VariantSpec is built

`VariantSpecFactory::create(width, height, filterSetName, poi, originalDimensions, context)`
merges three layers of raw, YAML-shaped config, in this order (later layers override
earlier ones):

1. the named `filter_sets.<name>` entry (via the `filter` prop / `filterSet` param),
2. the bundle-wide `image_configs`,
3. the per-call `context` (the `context` prop on `<twig:pgi:Image>`, or the equivalent
   query parameter for the "wait" URL).

The merge is recursive like `array_replace_recursive()`, **except list arrays are always
replaced wholesale, never merged element-by-index** — so a later layer's
`filters.resize.size: [400]` can't silently combine with an earlier layer's `[800, 600]`
into `[400, 600]`; you always get a clean, complete override or nothing.

**Sizing (`crop`/`thumbnail`) is never taken from that merge.** Any `crop`/`thumbnail`
contributed by a filter set, `image_configs`, or `context` is discarded, and the factory
always re-derives sizing itself from `(width, height, pointOfInterest, originalDimensions)`:

- with a point of interest → `AspectCropCalculator::calculate()` produces a `CropBox`
  around it, then `Crop` is applied **before** `Thumbnail::inset()` — crop always precedes
  thumbnail, so a point-of-interest crop is never overridden by a stray "centered"
  thumbnail;
- without one → `Thumbnail::outbound(width, height)`.

This guarantees the requested target size always wins, regardless of what a filter set
happens to also define.

## `filter_sets`

```yaml
progressive_image:
    filter_sets:
        thumbnail_square:
            filters:
                thumbnail: { size: [400, 400], mode: outbound }
            format: webp
            quality: 80
        watermarked:
            filters:
                watermark: { image: 'images/watermark.png', position: bottom_right, opacity: 70 }
```

```twig
<twig:pgi:Image src="{{ asset('images/hero.jpg') }}" filter="watermarked" alt="Hero" />
```

A typo in a filter name, or a malformed option, throws `InvalidFilterDefinition` — for a
`filter_sets` entry this happens **at compile time** (`ValidateFilterSetsPass` eagerly
constructs the registry during container compilation), so a broken config breaks
`cache:clear`/`cache:warmup`, not a live request.

## Available filters

Each block below is a ready-to-copy `filters` entry for a `filter_sets.<name>` definition.

**`thumbnail`** — `outbound` crops to exactly fill the box; `inset` fits within it.

```yaml
filters:
    thumbnail: { size: [400, 400], mode: outbound } # mode: outbound | inset
```

**`crop`** — crops a fixed box. `start`/`size` accept either a `[a, b]` list or
`{x: .., y: ..}` / `{width: .., height: ..}`.

```yaml
filters:
    crop:
        size: [400, 400]
        start: [0, 0] # default [0, 0]
```

**`resize`** — plain resize, no cropping.

```yaml
filters:
    resize: { size: [400, 400] }
```

**`rotate`** — angle is normalized into `[0, 360)`.

```yaml
filters:
    rotate: { angle: 90 }
```

**`background`** — fills transparent areas.

```yaml
filters:
    background: { color: '#ffffff' } # or '#rrggbbaa'
```

**`watermark`** — `image` is resolved the same way as any other source path.

```yaml
filters:
    watermark:
        image: 'images/watermark.png'
        position: bottom_right # default: center
        opacity: 70             # default: 100
```

**`grayscale`** — removes color information, no options.

```yaml
filters:
    grayscale: ~
```

**`negative`** — inverts colors, no options.

```yaml
filters:
    negative: ~
```

**`auto_rotate`** — rotates upright according to EXIF orientation, then discards the tag, no
options.

```yaml
filters:
    auto_rotate: ~
```

**`paste`** — pastes another source at an absolute offset from the top-left corner — unlike
`watermark`, no alignment/opacity, just a fixed position.

```yaml
filters:
    paste:
        image: 'images/logo.png'
        x: 10 # default: 0
        y: 10 # default: 0
```

**`relative_resize`** — scales relative to the image's dimensions **at that point in the
filter chain**, not the original source — e.g. after a `thumbnail`, `relative_resize` scales
the already-thumbnailed size. `50` halves it, `150` grows it by 50%. At least one of the two
options is required.

```yaml
filters:
    relative_resize: { width_percent: 50, height_percent: 50 }
```

Every filter's `canonical()` representation feeds directly into `VariantIdHasher` — two
specs that produce different `canonical()` output always get different `VariantId`s and
separate stored files.

## SVG sources are never generated

If a source path ends in `.svg` (case-insensitive), the Variant pipeline never attempts to
generate a raster variant for it — `ResolveVariantUrlHandler` and `ResolveFilterUrlHandler`
resolve straight to the original file's own URL instead, for both the responsive/breakpoint
path and `pgi_filter()`. This is deliberate, not a limitation to work around:

- SVGs are already infinitely scalable — there is no "size variant" to generate.
- Intervention Image can't rasterize SVG anyway; without this short-circuit, generation
  would just fail on every request.
- Critically, an SVG source would otherwise report as permanently "pending" (never actually
  ready), which forces `ResponseCacheOverrideListener` to mark the whole response `no-store`
  — silently disabling HTTP caching on any page that references one.

This is an extension check (the path literally ends in `.svg`), not content-sniffed —
correct for the near-universal case of a file actually named `.svg`, with no I/O cost.

## Formats & quality

```yaml
progressive_image:
    formats:
        default: jpeg          # jpeg | png | webp | avif
        default_quality: 85
        negotiate: [avif, webp] # tried in order against the Accept header before "default"
        quality:
            jpeg: 85
            webp: 82
            avif: 60
            png: 90
        progressive: false
        strip_metadata: false
```

- `formats.default` / `formats.default_quality` apply whenever a `filter_sets` entry,
  `image_configs`, or `context` doesn't set its own `format`/`quality`.
- `formats.negotiate` lets `VariantResponsiveImageUrlGenerator` pick the best format the
  requesting browser's `Accept` header actually supports, trying each listed format in
  order before falling back to `formats.default`.
- `png` quality is accepted but has no effect on the encoded output — `VariantSpec::canonical()`
  normalizes it to `0` so two PNG specs that only differ in a meaningless `quality` value
  don't hash to different `VariantId`s.
- `formats.progressive` — for JPEG, produces a progressive-scan JPEG; for PNG, an Adam7-interlaced
  one. No effect on WebP/AVIF (neither format has an equivalent concept). Like `format`/`quality`,
  it can be overridden per filter set or per-call `context` (`progressive: true`).
- `formats.strip_metadata` — strips EXIF/metadata on encode, for JPEG/WebP/AVIF (Intervention's
  PNG encoder has no such option). Also overridable per filter set/context (`strip_metadata: true`).
- Both flags are part of `VariantSpec::canonical()`, so two variants that differ only in
  `progressive`/`strip_metadata` get distinct `VariantId`s and separate stored files, never
  overwrite one another.

## Post-processors

Optional CLI re-encode/optimize step, run after `ImageManipulator` and before storage —
for tools that do a better job than Intervention's own encoder:

```yaml
progressive_image:
    post_processors:
        jpegoptim: { enabled: true }
        pngquant: { enabled: true }
        cwebp: { enabled: true } # re-encodes webp output at formats.quality.webp
        avifenc: { enabled: true } # re-encodes avif output at formats.quality.avif
```

Each requires its binary to exist on `$PATH` (or set `bin` to a full path) — checked
**at compile time** (`ValidatePostProcessorBinariesPass`), so a missing binary breaks
`cache:clear`, not a generation request. `cwebp`/`avifenc` fully replace Intervention's own
encoding for their format, so their configured quality is passed through explicitly rather
than falling back to the binary's own default.

See [Custom Post-Processor](/cookbook/custom-post-processor) to add your own.
