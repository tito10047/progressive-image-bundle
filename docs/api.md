# API Reference

Terse, lookup-oriented reference. For explanations and examples, follow the links into the
[Guide](/guide/getting-started) and [Cookbook](/cookbook/custom-storage-backend).

## `<twig:pgi:Image>` component

`Tito10047\ProgressiveImageBundle\Twig\Components\Image` — full description in
[The Twig Component](/guide/twig-component).

| Prop | Type | Default |
|:--|:--|:--|
| `src` | `?string` | `null` |
| `alt` | `?string` | `null` |
| `sizes` | `?string` | `null` |
| `ratio` | `?string` | `null` |
| `filter` | `?string` | `null` |
| `context` | `array` | `[]` |
| `pointInterest` | `?string` (`"X0xY0"`) | `null` |
| `retina` | `?bool` | bundle default |
| `preload` | `bool` | `false` |
| `priority` | `string` | `'high'` |
| `ttl` | `?int` | bundle default |

## Variant Domain ports (`Variant\Domain\Port\*`)

| Interface | Method(s) | Built-in adapter |
|:--|:--|:--|
| `ImageManipulator` | `process(SourceImage, VariantSpec): GeneratedImage` | `InterventionImageManipulator` |
| `PostProcessor` | `supports(OutputFormat): bool`; `process(GeneratedImage): GeneratedImage` | `Jpegoptim`/`Pngquant`/`Cwebp`/`AvifencPostProcessor` |
| `SourceReader` | `read(SourcePath): SourceImage` | `ResolverChainSourceReader` |
| `VariantStorage` | `exists`, `write`, `read`, `delete`, `publicPath`, `writeFailMarker`, `failMarkerTimestamp` | `FlysystemVariantStorage` |
| `GenerationLock` | `acquire(VariantId): ?Lock`; `release(Lock): void` | `SymfonyGenerationLock` |
| `Lock` | *(opaque marker)* | `SymfonyLock` |
| `Clock` | `now(): DateTimeImmutable` | `SystemClock` |

## Variant Application ports (`Variant\Application\Port\*`)

| Interface | Method(s) | Built-in adapter |
|:--|:--|:--|
| `GenerationDispatcher` | `dispatch(GenerateVariant): void` | `Sync`/`Terminate`/`MessengerGenerationDispatcher` |
| `DomainEventBus` | `publish(object): void` | `SymfonyDomainEventBus` |
| `OriginalUrlResolver` | `resolve(SourcePath): string` | `DefaultOriginalUrlResolver` |
| `PendingUrlBuilder` | `build(ResolveVariantUrl): string` | `QueryPendingUrlBuilder` |
| `UrlSigner` | `sign(string): string`; `check(string): bool` | `SymfonyUriSigner` |

## Rendering-context extension interfaces

| Interface | Method(s) | Config hook |
|:--|:--|:--|
| `UrlGenerator\ResponsiveImageUrlGeneratorInterface` | `generateUrl(path, targetW, targetH?, pointInterest?, context?): string` | `responsive_strategy.generator` |
| `Modifier\ModifierInterface` | `supports(string): bool`; `modify(string, array): array` | autoconfigured → tag `progressive_image.modifier` |
| `Modifier\FilterModifierInterface` | `supports(string): bool`; `modify(string, array): array` | autoconfigured → tag `pgi.filter_modifier` (currently unconsumed) |
| `Decorators\PathDecoratorInterface` | `decorate(path, context?): string`; `getSize(path, context?): array\|null` | `path_decorators` (explicit service id list) |

## Domain model (`Variant\Domain\Model\*`)

| Type | Shape |
|:--|:--|
| `Variant` | aggregate: `id`, `source`, `spec`, `state` — `Variant::request()`, `startGenerating()`, `markReady()`, `markFailed()` |
| `VariantId` | wraps a base64url HMAC-SHA256 string |
| `VariantSpec` | `{filters: FilterChain, format: OutputFormat, quality: Quality}` |
| `VariantPath` | `VariantPath::for(VariantId, SourcePath, OutputFormat): self` — `{format}/{shard}/{id}/{source}.{ext}` |
| `SourcePath` / `SourceImage` | logical source path / already-read `{stream, dimensions, mime}` |
| `GeneratedImage` | `{contents: string, format: OutputFormat}` |
| `Dimensions` | `{width: int, height: int}` |
| `CropBox` | `{startX, startY, size: Dimensions}` |
| `PointOfInterest` | `{x: int, y: int}` (non-negative) |
| `OutputFormat` | enum: `Jpeg`, `Png`, `Webp`, `Avif` |
| `Quality` | wraps an `int` |

## Filters (`Variant\Domain\Filter\*`)

All implement `Filter { canonical(): array }` and compose into an immutable `FilterChain`.
See [Filters, Formats & Quality](/guide/variant-pipeline/filters-formats-and-quality#available-filters)
for the raw-config shape each maps from.

`Crop`, `Resize`, `Rotate`, `Background`, `Watermark` (+ `WatermarkPosition` enum),
`Thumbnail` (+ `ThumbnailMode` enum: `Inset`, `Outbound`).

## Domain events

Published via `DomainEventBus::publish()` from `GenerateVariantHandler`:

- `VariantRequested`
- `VariantGenerated`
- `VariantGenerationFailed`

## Console command

```bash
php bin/console progressive-image:generate-custom-css [path=assets/styles]
```

Generates responsive CSS custom properties from `responsive_strategy.grid` — see
[Responsive Grid & Ratios](/guide/responsive-grid-and-ratios#generating-the-css).

## Routes

| Name | Path | Method | Purpose |
|:--|:--|:--|:--|
| `pgi_variant_serve` | `/media/pgi/wait` | `GET` | Signed endpoint used by `fallback_while_pending: wait`; see [Generation Strategies](/guide/variant-pipeline/generation-strategies#serving-the-wait-fallback). |

Import via:

```yaml
progressive_image:
    resource: "@ProgressiveImageBundle/config/routes.php"
```
