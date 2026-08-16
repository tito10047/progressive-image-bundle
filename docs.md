# Facts — progressive-image-bundle (branch v2.0, post-Liip-removal)

Raw facts only, gathered from the actual code as of this branch's current state. No
tutorial/marketing prose. Source material for writing full documentation later.

## 1. Two bounded contexts

- **Rendering context** (`src/`, outside `src/Variant/`): the `<pgi:Image>` Twig component,
  responsive srcset/sizes computation, preload headers, BlurHash metadata, modifiers. Works
  standalone — does not require the Variant pipeline to be configured.
- **Variant context** (`src/Variant/`): the image-generation pipeline (crop/resize/format
  conversion, storage, async/sync generation, serving). Fully optional — only wired when
  `progressive_image.variant_store.storage` is set. Built with DDD/hexagonal architecture:
  `Domain/` (no framework deps), `Application/` (use cases), `Infrastructure/` (adapters).
- Bridge between the two: `ResponsiveImageUrlGeneratorInterface` (Rendering context depends
  on it; Variant context provides `VariantResponsiveImageUrlGenerator` as one implementation
  among several possible ones). Also `PendingGenerationTracker` (Variant → HTTP-cache-guard
  shared kernel, see §7).
- If neither the Variant pipeline nor a custom `responsive_strategy.generator` nor
  `responsive_strategy.grid` is configured, `<pgi:Image>` renders `src` as-is with no
  responsive/URL processing at all.

## 2. Full `progressive_image` config schema

Source: `src/DependencyInjection/Configuration.php`. All keys, in schema order.

```yaml
progressive_image:
    resolvers:                          # name => { type, roots, allowUnresolvable, resolvers }
        <name>:
            type: filesystem|asset_mapper|chain    # required
            roots: [ string, ... ]                  # required for type: filesystem
            allowUnresolvable: false                 # bool, filesystem only
            resolvers: [ name, ... ]                 # required for type: chain
    driver: gd                          # gd|imagick
    loader: null                        # scalar, service id override
    resolver: default                   # which entry in `resolvers` to use as default
    cache: null                         # scalar, PSR-6 pool id (MetadataReader cache)
    image_cache_service: cache.app      # scalar, service id
    hash_resolution:
        width: 10
        height: 8
    fallback_image: null                # scalar path
    image_cache_enabled: false          # bool
    ttl: null                           # int|null
    retina:
        enabled: true
        multipliers: [1, 2]
    responsive_strategy:
        generator: null                 # scalar service id; custom ResponsiveImageUrlGeneratorInterface.
                                         # Overrides default/Variant-pipeline generator when set.
                                         # NOTE: added in D8 — was read by ProgressiveImageExtension
                                         # but never declared in the schema before, so it could never
                                         # actually be set until this fix.
        grid:
            framework: custom           # custom|bootstrap|tailwind
                                         # bootstrap/tailwind auto-fill `columns`/`gutter`/`layouts`
                                         # defaults (see Configuration.php lines ~113-138 for exact values)
            columns: 12
            gutter: 24
            layouts:                    # name => { min_viewport, max_container }
                <name>:
                    min_viewport: int
                    max_container: int|null   # null = 100vw
        ratios:                         # name => ratio string ("16/9", "0.65", "400x500", etc.)
            <name>: string
    path_decorators: []                 # list of PathDecoratorInterface service ids
    image_configs: []                   # arbitrary array, merged into VariantSpecFactory's
                                         # imageConfigs layer (see §5.4) — variable/untyped node
    secret: null                        # scalar; VariantIdHasher secret. Defaults to %kernel.secret%
                                         # when null (set at extension load time, not in schema).
    variant_store:
        storage: null                   # scalar service id (League\Flysystem\FilesystemOperator).
                                         # NULL = Variant context is NOT wired at all (opt-in gate).
        prefix: ''                      # scalar, path prefix inside the storage
        public_url_prefix: /media/pgi   # scalar, prepended to VariantPath for the public URL
        fail_marker_ttl: 300            # int seconds
    generation:
        strategy: async                 # async|sync|terminate
        transport: async_images         # scalar, Messenger transport name (only used if strategy=async)
        fallback_while_pending: original  # original|wait
        lock_store: null                # scalar; null = FlockStore(%kernel.cache_dir%/pgi-locks),
                                         # else passed to Symfony\Component\Lock\Store\StoreFactory::createStore()
    formats:
        default: jpeg                   # jpeg|png|webp|avif
        default_quality: 85             # int
        negotiate: []                   # list of jpeg|png|webp|avif, tried in order against Accept header
        quality:                        # per-format quality map, used when a format is negotiated
            jpeg: 85
            webp: 82
            avif: 60
            png: 90
    filter_sets:                        # name => { filters: {...}, format?: string, quality?: int }
        <name>: variable                # validated at compile time by ValidateFilterSetsPass
    post_processors:
        jpegoptim: { enabled: false, bin: jpegoptim }
        pngquant:  { enabled: false, bin: pngquant }
        cwebp:     { enabled: false, bin: cwebp }
        avifenc:   { enabled: false, bin: avifenc }
        # enabled:true + unresolvable bin = LogicException at compile time
        # (ValidatePostProcessorBinariesPass)
```

### Compiler passes (fail fast at `cache:clear`, not at request time)
- `CheckCacheInterfacePass` — validates `image_cache_service` implements `TagAwareCacheInterface`
  when `image_cache_enabled: true`.
- `ValidateFilterSetsPass` — constructs a real `FilterSetRegistry` from `filter_sets` at compile
  time; throws `LogicException` (wrapping `InvalidFilterDefinition`) on any typo/malformed filter.
- `ValidatePostProcessorBinariesPass` — for each `post_processors.<name>.enabled: true`, resolves
  `bin` (via `Symfony\Component\Process\ExecutableFinder` for bare names, `is_executable()` directly
  for paths containing a separator) and throws `LogicException` if not found.

## 3. `<pgi:Image>` Twig component

Class: `src/Twig/Components/Image.php`, `#[AsTwigComponent]`, component name `pgi:Image`
(prefix `pgi` configured via `name_prefix` in `twig_component.defaults`).

Public props (settable from Twig):
```
src: ?string
filter: ?string              # sets context['filter'] internally at postMount
alt: ?string
pointInterest: ?string       # "XxY" pixel coordinates, e.g. "544x320"
context: array<string,mixed> # arbitrary, merged/passed to ResponsiveAttributeGenerator/URL generator
preload: bool = false
ttl: ?int
priority: string = 'high'    # preload priority
sizes: ?string                # breakpoint DSL, see §4
ratio: ?string                 # default ratio applied to all breakpoint assignments without one
retina: ?bool                  # null = use bundle default (retina.enabled)
```

Public read methods: `getSrcset()`, `getResponsiveSizes()`, `getResponsiveAttributes()`,
`hasResponsiveAttributes()`, `getHash()` (BlurHash), `getWidth()`, `getHeight()`,
`getVariables()` (CSS custom properties map, e.g. `--img-width`, `--img-aspect`,
`--img-width-<breakpoint>`), `getDecoratedSrc()`, `getController()` (Stimulus controller id,
`ProgressiveImageBundle::STIMULUS_CONTROLLER`), `getFramework()`.

`postMount()` behavior:
1. `retina` defaults to bundle's `retina.enabled` if null.
2. If `src` is set: runs `src` through every registered `PathDecoratorInterface::decorate()`
   in order (`path_decorators` config) → `decoratedSrc`.
3. Reads BlurHash + dimensions via `MetadataReaderInterface::getMetadata($src)` (catches
   `PathResolutionException` silently → null metadata).
4. Parses `sizes` into `BreakpointAssignment[]` (§4).
5. If breakpoints exist AND a `ResponsiveAttributeGenerator` is wired: calls
   `generate($src, $breakpoints, $metadata->width, $preload, $pointInterest, $context, $retina, $metadata->height)`.
6. Else (no breakpoints / no generator): dimensions come from metadata or the first
   `PathDecoratorInterface::getSize()` that returns non-null; if `preload`, adds
   `decoratedSrc` directly to `PreloadCollector`.

## 4. Breakpoint DSL (`sizes` attribute)

Parsed by `DTO\BreakpointAssignment::parseSegments(string $segments, ?string $defaultRatio)`.
Segments are space-separated. Each segment:
```
[breakpoint:]value[@ratio][|modifier1|modifier2...]
```
- `breakpoint:` — omit for the "default" breakpoint.
- `value` forms: `N` (grid columns, integer) | `[NxM]` (explicit pixel width x height) |
  `[N]` (explicit pixel width) | `[N%]` (percentage width).
- `@ratio` — named ratio (from `responsive_strategy.ratios`), `"W/H"`, a bare float
  (`"0.65"`), or `"WxH"`. If height was given explicitly and no `@ratio`, ratio is derived
  from width/height.
- `|modifier` — appended to the modifiers list, resolved via `ModifierProvider`/
  `ModifierInterface` (tag `progressive_image.modifier`, priority-ordered, higher priority
  wins). `BaseFilterModifier` (priority -100) sets `context['filter'] = <modifier>` as the
  fallback when nothing else claims it.

Example: `xs:12 sm:6@landscape md:[400x300] lg:[50%]@0.65|circle`

## 5. Variant context — architecture facts

### 5.1 Directory layout
```
src/Variant/
├── Domain/          # Model/, Filter/, Service/, Event/, Exception/, Port/ — zero framework deps
├── Application/      # Command/, Handler/, Query/, Service/, Port/
└── Infrastructure/    # Flysystem/, Intervention/, Lock/, Messenger/, Terminate/, Sync/,
                        # PostProcess/, Presentation/ (Controller/, EventListener/, UrlGenerator/), Source/, Symfony/
```
`deptrac.yaml` enforces: Domain depends on nothing; Application depends only on Domain;
Infrastructure depends on Application+Domain. `php vendor/bin/deptrac analyse` = 0 violations
as of this commit.

### 5.2 Domain value objects
`Dimensions` (width/height >0), `Quality` (1-100), `PointOfInterest` (x,y ≥0), `SourcePath`
(normalized, rejects `..` traversal), `OutputFormat` (enum: Jpeg|Png|Webp|Avif, `mime()`/
`extension()`), `CropBox` (start+size, `::within()` bounds-checked factory), `VariantId`
(wraps a string), `VariantSpec` (FilterChain+OutputFormat+Quality, `canonical()`),
`VariantPath` (single source of truth for the store layout: `{format}/{ab-shard}/{hash}/{source-path}.{ext}`,
built via `VariantPath::for(VariantId, SourcePath, OutputFormat)`), `VariantState` (enum:
Requested|Generating|Ready|Failed), `SourceImage` (stream+Dimensions+mime), `GeneratedImage`
(contents+OutputFormat, `withContents()`).

### 5.3 Domain filters (`Domain/Filter/`)
`Filter` interface (`canonical(): array`), `FilterChain` (immutable list, `of()`/`with()`/
`without(...classes)`/`canonical()`, implements `IteratorAggregate`+`Countable`).
Concrete filters: `Thumbnail` (`::inset()`/`::outbound()`, `ThumbnailMode` enum), `Crop`
(wraps `CropBox`), `Resize`, `Rotate` (normalizes degrees to `[0,360)`), `Background`
(`#rrggbb`/`#rrggbbaa` hex, validated), `Watermark` (SourcePath + `WatermarkPosition` enum:
TopLeft|TopRight|Top|BottomLeft|BottomRight|Bottom|Center + opacity 0-100).

### 5.4 Domain services
- `AspectCropCalculator::calculate(PointOfInterest, target: Dimensions, original: Dimensions): CropBox`
  — POI crop math, ported verbatim (including integer rounding) from the bundle's pre-DDD
  runtime-filter generator, for pixel parity. Golden dataset in `AspectCropCalculatorTest`.
- `VariantIdHasher::hash(SourcePath, VariantSpec): VariantId` — HMAC-SHA256 over canonical
  JSON `{src, spec: spec.canonical(), v: 1}`, base64url-encoded. `v` = hash-schema version,
  bump deliberately if the canonical shape ever changes (invalidates every existing VariantId).
  Golden hashes frozen in `VariantIdHasherTest`.

### 5.5 Domain aggregate
`Variant` (`src/Variant/Domain/Model/Variant.php`): identity = content-addressed `VariantId`
(same source+spec ⇒ same id ⇒ idempotent generation for free). Lifecycle:
`Variant::request(SourcePath, VariantSpec, VariantIdHasher): self` (state=Requested) →
`startGenerating()` (throws if already Ready) → `markReady(): VariantGenerated` (throws if
not Generating) / `markFailed(Throwable): VariantGenerationFailed` (throws if not
Generating). `path(): VariantPath` derived from id+source+spec.format. Domain events:
`VariantRequested`, `VariantGenerated`, `VariantGenerationFailed` (public API — dispatched
under their own class name via Symfony's EventDispatcher, for user logging/metrics).

### 5.6 Domain ports (interfaces only; Infrastructure implements)
`ImageManipulator::process(SourceImage, VariantSpec): GeneratedImage`.
`VariantStorage::exists/write/read/delete(VariantPath)`, `publicPath(VariantPath): string`,
`writeFailMarker(VariantPath, DateTimeImmutable)`, `failMarkerTimestamp(VariantPath): ?DateTimeImmutable`.
`SourceReader::read(SourcePath): SourceImage` (throws `SourceNotReadable`).
`GenerationLock::acquire(VariantId): ?Lock`, `release(Lock)`.
`PostProcessor::supports(OutputFormat): bool`, `process(GeneratedImage): GeneratedImage`.
`Clock::now(): DateTimeImmutable`. `Lock` — opaque marker interface.

### 5.7 Application layer
- `FilterFactory` — raw YAML-shaped filter config (`name => options`) → typed `Filter` VO.
  Unknown names/malformed options throw `InvalidFilterDefinition` (never silently ignored).
- `FilterSetRegistry` — immutable, boot-time-validated (constructor throws on any bad entry)
  snapshot of `filter_sets` config. Stores raw arrays (not pre-parsed FilterChains) because
  `VariantSpecFactory` still needs to recursively merge them with `imageConfigs` and per-call
  context before typing the result.
- `VariantSpecFactory::create(width, height, ?filterSetName, ?PointOfInterest, ?originalDimensions, context): VariantSpec`
  — replaces the old runtime-filter generator. Merge order: filter set → `imageConfigs`
  (bundle config) → per-call context, `array_replace_recursive`, matching legacy behavior.
  The sizing crop/thumbnail is ALWAYS the factory's own construction — any crop/thumbnail
  from the merge is stripped first (`FilterChain::without(Crop::class, Thumbnail::class)`),
  then Crop+Thumbnail::inset (POI given) or Thumbnail::outbound (no POI) is appended. `format`/
  `quality` resolved from the same merged config, falling back to factory defaults.
- `PendingGenerationTracker` — per-request set of pending `VariantId`s. `markPending()`,
  `hasPending()`, `reset()` (tagged `kernel.reset` in DI). The one shared-kernel class the
  Variant context exposes to the Rendering/HTTP-cache-guard code (see §7).
- `GenerateVariant` command — `SourcePath` + `VariantSpec` only (no service refs, no
  `VariantId` — handler recomputes it deterministically via `VariantIdHasher`).
- `GenerateVariantHandler` — the ONE generation code path (sync/terminate/async dispatch
  strategies all call it). Flow: acquire lock (null → no-op return) → storage exists? (→
  no-op return, idempotent) → fresh fail marker? (→ no-op return, backoff) → startGenerating
  → read source → manipulate → run supporting post-processors → write to storage → publish
  `VariantGenerated` / on any exception: write fail marker, publish `VariantGenerationFailed`,
  rethrow → finally release lock.
- `ResolveVariantUrl` query (SourcePath, width, height, ?filterSet, ?PointOfInterest,
  ?originalDimensions, context) + `ResolvedUrl` (url, pending: bool) +
  `PendingFallbackStrategy` enum (Original|Wait).
- `ResolveVariantUrlHandler` — the hot path (every render). Stateful: memoizes per
  `VariantId` for its own lifetime (must be wired non-shared/request-scoped). Flow: build
  spec → compute Variant → memo hit? return cached → storage exists? → HIT: return public
  path, pending=false. MISS: mark pending in tracker, dispatch `GenerateVariant`, resolve
  fallback URL (Original: `OriginalUrlResolver::resolve()`; Wait: sign
  `PendingUrlBuilder::build($query)` via `UrlSigner`), pending=true.
- Application ports: `GenerationDispatcher::dispatch(GenerateVariant)`,
  `DomainEventBus::publish(object)`, `UrlSigner::sign/check(string): string/bool`,
  `OriginalUrlResolver::resolve(SourcePath): string` (not in the original DDD plan's port
  list — added because the "original" fallback needed it),
  `PendingUrlBuilder::build(ResolveVariantUrl): string` (also added; deliberately takes the
  *original query*, not the resolved VariantSpec, so the serving controller can rebuild an
  identical VariantSpec via `VariantSpecFactory::create()` rather than deserializing
  arbitrary objects from a request).

### 5.8 Infrastructure adapters
| Port | Adapter | Notes |
|---|---|---|
| `ImageManipulator` | `InterventionImageManipulator` | intervention/image v4, `match` on `$filter::class`. Thumbnail::outbound→`cover()`, ::inset→`scale()`, Crop→`crop()`, Resize→`resize()`, Rotate→`rotate()`, Background→`fillTransparentAreas()`. Watermark needs a 2nd `SourceReader::read()` call for the mark image. |
| `VariantStorage` | `FlysystemVariantStorage` | Single class for local AND cloud — only cares about `League\Flysystem\FilesystemOperator`. Atomic write: temp-path-then-move. Format is parsed back from `VariantPath`'s leading path segment on read (not duplicated in metadata). |
| `SourceReader` | `ResolverChainSourceReader` | Wraps existing `PathResolverInterface`+`LoaderInterface` (Rendering context, reused not reinvented). Dimensions/mime via `getimagesize()`. |
| `GenerationLock` | `SymfonyGenerationLock` | Wraps `symfony/lock` `LockFactory` — store-agnostic (flock local / Redis cluster is DI wiring only). |
| `GenerationDispatcher` | `SyncGenerationDispatcher` / `TerminateGenerationDispatcher` / `MessengerGenerationDispatcher` | Sync: runs handler in-request. Terminate: queues, flushes on `onTerminate()` (tagged `kernel.terminate`). Messenger: per-request dedup by VariantId, puts `GenerateVariant` straight on the bus; `GenerateVariantMessageHandler` (`#[AsMessageHandler]`, tagged `messenger.message_handler` explicitly since services aren't autoconfigured) is a 3-line delegate to the Application handler. |
| `UrlSigner` | `SymfonyUriSigner` | Wraps Symfony's `UriSigner`. |
| `DomainEventBus` | `SymfonyDomainEventBus` | Wraps `EventDispatcherInterface`. |
| `Clock` | `SystemClock` | `new DateTimeImmutable()`. |
| `OriginalUrlResolver` | `DefaultOriginalUrlResolver` | `'/'.$source->value` — mirrors `DefaultResponsiveImageUrlGenerator`'s convention. |
| `PendingUrlBuilder` | `QueryPendingUrlBuilder` | Encodes `ResolveVariantUrl` as explicit named route params (`source`, `width`, `height`, `filterSet`, `poiX/Y`, `origW/H`, `context` as JSON) on route `pgi_variant_serve`, human-readable not a serialized blob. |
| `PostProcessor` | `JpegoptimPostProcessor`/`PngquantPostProcessor`/`CwebpPostProcessor`/`AvifencPostProcessor` (base: `CliPostProcessor`) | Writes encoded bytes to temp file, runs binary, reads result, any failure throws (treated as generation failure). `avifenc` can only encode TO avif from PNG/JPEG/y4m (confirmed: fails with "Unrecognized file format" on AVIF input) — `AvifencPostProcessor` overrides `inputExtension()`/`inputContents()` to decode AVIF→PNG first. |

### 5.9 Presentation
- `ImageVariantController::serve()` — route `pgi_variant_serve` → `/media/pgi/wait`. Handles
  both the "wait" fallback's signed URL AND an nginx try_files miss on an evicted variant
  path. Requires a valid signature (checked via `UrlSigner::check($request->getUri())`) —
  deliberately no separate path-parameter route exists, since a bare path can't be
  regenerated without the full spec (anti-enumeration). On miss: rebuilds `VariantSpec` via
  `VariantSpecFactory::create()` from query params, generates synchronously (catches +
  logs failure via optional PSR-3 logger, never silently), always redirects (302) — never
  streams — to the storage's public path, or to `OriginalUrlResolver` with `no-store` if
  generation still failed.
- `VariantResponsiveImageUrlGenerator` implements `ResponsiveImageUrlGeneratorInterface`
  (the Rendering-context seam). Reads original dimensions via `MetadataReaderInterface` only
  when `pointInterest` is given (avoids an extra cache lookup otherwise). Format negotiation
  (`formats.negotiate` against the `Accept` header, via injected `RequestStack`) lives HERE,
  not in `VariantSpecFactory` — sets `context['format']`/`context['quality']` before
  resolving; an explicit `context['format']` from the caller always wins over negotiation.
- `ResponseCacheOverrideListener` — `kernel.response`, priority **-1024** (runs after
  Symfony's `#[Cache]`/`CacheAttributeListener`). If `PendingGenerationTracker::hasPending()`:
  overrides `Cache-Control` to `no-store, no-cache, must-revalidate, private, max-age=0`,
  strips `ETag`/`Last-Modified`/`Expires`, adds `Surrogate-Control: no-store`.

## 6. Wiring facts (`ProgressiveImageExtension`)

- Variant context wiring (`configureVariantContext()`) runs unconditionally for
  `filter_sets` parameter-setting + post-processor parameter-setting (so
  `ValidateFilterSetsPass`/`ValidatePostProcessorBinariesPass` catch typos even if the
  pipeline isn't opted into) but returns early — no services registered — if
  `variant_store.storage` is null.
- When active, `VariantResponsiveImageUrlGenerator` unconditionally wins the
  `ResponsiveImageUrlGeneratorInterface` alias at the very end of `load()` (opting into
  `variant_store.storage` is an explicit signal), overriding any earlier
  default/custom-generator alias.
- `ResolveVariantUrlHandler` is registered `->setShared(false)` (fresh instance per fetch —
  its own memoization must not leak across requests/injection points in the standard
  single-request-per-container-boot deployment model; worker-mode runtimes are out of scope,
  not handled).
- `TransparentCacheExtension` (Twig fragment cache) gets a `PendingGenerationTracker`
  reference ONLY when `variant_store.storage` is configured; otherwise the param stays null
  (nullable, backward compatible) and it behaves exactly as before Liip removal.
- `Intervention\Image\ImageManager` driver picked from the existing top-level `driver`
  config key (gd/imagick) — same key the old GD/Imagick analyzer already used, single
  source of truth.

## 7. HTTP-cache-guard / shared-kernel facts

- `PendingGenerationTracker` is the ONLY class crossing from the Variant context into the
  (unchanged) Rendering/cache code. Two consumers, both with the tracker as an **optional**
  dependency (nullable, defaults to inert behavior) so non-Variant installs are unaffected:
  1. `ResponseCacheOverrideListener` (Variant-side, only registered when Variant is active).
  2. `Twig\TransparentCacheExtension::saveToCache()` — skips writing to the PSR-6 fragment
     cache while `hasPending()` is true (a fragment referencing a still-generating variant
     must not get frozen into the cache).

## 8. Test infrastructure facts

- `deptrac.yaml` at repo root; `php vendor/bin/deptrac analyse` — 0 violations.
- `phpstan.neon`: default level 6, `paths: [src]`. `src/Variant/` is additionally held to
  level max (verify via `php vendor/bin/phpstan analyse src/Variant tests/Variant --level max`).
  **Always invoke as `php vendor/bin/phpstan ...`** — bare `vendor/bin/phpstan` was denied by
  this session's permission system for unclear reasons.
- Contract-testing pattern (plan §10): `tests/Variant/Contract/VariantStorageContractTest`
  (abstract) runs the same assertions against both `InMemoryVariantStorage` (fake) and
  `FlysystemVariantStorage` backed by a real local adapter — proves the fake isn't lying.
  Reuse this pattern for any future `VariantStorage`-like port with multiple adapters.
- `tests/Integration/ProgressiveImageTestingKernel` — shared test kernel. Manually
  re-registers routes in `loadRoutes()` rather than importing the bundle's
  `config/routes.php` (any new route needs adding in both places). Registers two reusable
  fixture services unconditionally: `test.fake_filter_path_decorator`
  (`FakeFilterPathDecorator`, implements `PathDecoratorInterface`) and
  `test.fake_dimensions_url_generator` (`FakeDimensionsEchoingUrlGenerator`, implements
  `ResponsiveImageUrlGeneratorInterface`) — reuse these before writing new one-off fixtures.
- `PGITestCase::bootKernel(array $options = [], ?\Closure $customConfiguration = null)` —
  the closure is threaded to `ProgressiveImageTestingKernel::setCustomConfiguration()`,
  letting tests register services before boot without constructing the kernel directly.
- `tests/Variant/Double/` — in-memory fakes/spies for all Domain/Application ports
  (`InMemoryVariantStorage`, `InMemoryGenerationLock`, `FakeSourceReader`,
  `FakeImageManipulator`, `SpyDomainEventBus`, `FrozenClock`, `SpyGenerationDispatcher`,
  `FakeOriginalUrlResolver`, `FakePendingUrlBuilder`, `FakeUrlSigner`, `FakeUrlGenerator`,
  `SpyMessageBus`).
- PHPUnit is v13: use `#[DataProvider]` attributes, NOT `@dataProvider` annotations
  (annotations no longer work).
- All 4 post-processor CLI binaries (`jpegoptim`, `pngquant`, `cwebp`, `avifenc`) are
  installed in this dev environment — their tests run for real, no mocked CLI calls, skip
  only if the binary is genuinely absent (`ExecutableFinder`-based skip guard).

## 9. Composer dependency facts

- `require` (production, always installed): `intervention/image` (`^4.2`), `kornrunner/blurhash`,
  `league/flysystem` (`^3.35`), `symfony/asset`, `symfony/framework-bundle`, `symfony/lock`
  (`^8.1`), `symfony/process` (`^8.1`), `symfony/stimulus-bundle`, `symfony/translation`,
  `symfony/twig-bundle`, `symfony/ux-twig-component`, `symfony/web-link`.
- `require-dev` only (optional for consumers, needed for this repo's own dev/test):
  `oneup/flysystem-bundle` (`^4.14`, DI wiring for Flysystem adapters), `symfony/messenger`
  (`^8.1`, only needed if `generation.strategy: async`), `deptrac/deptrac`, phpstan,
  php-cs-fixer, symfony test-pack/dotenv/yaml/asset-mapper, `ext-imagick`.
- `liip/imagine-bundle` and its transitive deps (`imagine/imagine`, etc.) are REMOVED
  entirely — not in require or require-dev. `composer show | grep liip` is empty.
- `grep -ri liip src/ config/ tests/` is clean except
  `src/Resolver/FileSystemResolver.php`'s copyright header ("This file was part of the
  `liip/LiipImagineBundle` project... (c) https://github.com/liip/LiipImagineBundle/graphs/contributors")
  — a legitimate third-party MIT attribution notice for code lineage, deliberately kept, not
  a dependency reference.

## 10. Routes

- `pgi_variant_serve` → `/media/pgi/wait`, `ImageVariantController::serve`, GET only.
  Registered in `config/routes.php` (production) AND manually duplicated in
  `tests/Integration/ProgressiveImageTestingKernel::loadRoutes()` (test kernel does not
  import the bundle's routes.php).
- The old `progressive_image_filter` route (Liip controller) is gone — no replacement route
  exists for it; Liip's runtime-filter URL scheme has no equivalent.

## 11. Console command

`progressive-image:generate-custom-css` (`GenerateCustomCssCommand`) — generates a custom
Tailwind CSS file from `responsive_strategy.grid` config. Unrelated to the Variant pipeline,
untouched by the Liip removal.

## 12. Versioning facts

- Latest git tag: `1.2.1`. Latest `CHANGELOG.md` entry: `[1.2.0] - 2026-08-14`.
  Changelog format: Keep a Changelog + SemVer.
- Branch name `v2.0` matches what SemVer requires for this change (removing
  `liip_imagine` config support entirely is breaking) — no version bump has been made yet
  in `composer.json`/CHANGELOG.md as of this commit; composer.json has no explicit "version"
  key (bundle versioning is via git tags).
- Root-level planning docs not yet cleaned up: `forgot_liip.md` (superseded, says so in its
  own header) and `plan_liip_removal_ddd.md` (the plan this entire branch implements) still
  exist at repo root. Plan's own D9 says their content should move into `docs/` and the
  files themselves deleted once documented.

## 13. Deliberately deferred / open items

- **D7 (cloud/minio E2E)** — skipped per explicit user decision (2026-08-15). Reasoning:
  `FlysystemVariantStorage` only depends on `League\Flysystem\FilesystemOperator`, already
  proven adapter-agnostic by the D3 contract test; an S3 adapter is Flysystem's own
  well-tested code, not this bundle's.
- **`PurgeVariants` command/handler** — named in the original DDD plan's directory tree but
  has zero behavioral spec (no fields, no invalidation strategy given the content-addressed
  store has no reverse index from source path → variant ids). Not implemented. Needs a
  design decision from the user before it can be built.
- **`fallback_while_pending: wait` + `generation.strategy: async` combination** — works
  (verified end-to-end in `VariantContextWiringTest`), but note the "wait" URL always
  triggers SYNCHRONOUS generation in `ImageVariantController` regardless of the configured
  async strategy — the controller doesn't dispatch, it generates directly via
  `GenerateVariantHandler`.
- Existing `docs/*.md` (`index.md`, `installation.md`, `usage.md`, `configuration.md`,
  `quickstart.md`, `advanced.md`, `article.md`) and `docs/config_example.yaml` all still
  reference Liip and are NOT yet updated to describe the Variant pipeline — deliberately
  left alone this pass per user instruction (facts-first, prose docs later).
