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

| Filter | Options | Notes |
|:--|:--|:--|
| `thumbnail` | `size: [w, h]`, `mode: outbound\|inset` | `outbound` crops to exactly fill the box; `inset` fits within it. |
| `crop` | `size: [w, h]`, `start: [x, y]` (default `[0, 0]`) | Crops a fixed box. `start`/`size` accept either a `[a, b]` list or `{x:.., y:..}` / `{width:.., height:..}`. |
| `resize` | `size: [w, h]` | Plain resize, no cropping. |
| `rotate` | `angle: <int degrees>` | Normalized into `[0, 360)`. |
| `background` | `color: '#rrggbb'` or `'#rrggbbaa'` | Fills transparent areas. |
| `watermark` | `image: <path>`, `position: top_left\|top_right\|top\|bottom_left\|bottom_right\|bottom\|center` (default `center`), `opacity: 0-100` (default `100`) | `image` is resolved the same way as any other source path. |

Every filter's `canonical()` representation feeds directly into `VariantIdHasher` — two
specs that produce different `canonical()` output always get different `VariantId`s and
separate stored files.

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
```

- `formats.default` / `formats.default_quality` apply whenever a `filter_sets` entry,
  `image_configs`, or `context` doesn't set its own `format`/`quality`.
- `formats.negotiate` lets `VariantResponsiveImageUrlGenerator` pick the best format the
  requesting browser's `Accept` header actually supports, trying each listed format in
  order before falling back to `formats.default`.
- `png` quality is accepted but has no effect on the encoded output — `VariantSpec::canonical()`
  normalizes it to `0` so two PNG specs that only differ in a meaningless `quality` value
  don't hash to different `VariantId`s.

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
