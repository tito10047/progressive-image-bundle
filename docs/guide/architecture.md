# Architecture

If you're only using the bundle, you don't need this page. It's for anyone contributing
to it, or implementing one of the extension points in the [Cookbook](/cookbook/custom-storage-backend)
and wanting to know where new code belongs.

## Two subsystems

The codebase under `src/` is really two layers, from two different eras:

1. **The rendering context** — `Analyzer`, `Command`, `Decorators`, `DependencyInjection`,
   `DTO`, `Event`, `Exception`, `Loader`, `Modifier`, `Resolver`, `Service`, `Twig`,
   `UrlGenerator`. Responsible for the `<twig:pgi:Image>` component, Blurhash placeholder
   metadata, responsive `sizes`/grid math, preload hints, and transparent HTML fragment
   caching. Predates the DDD rewrite; not formally layered.
2. **The Variant context** — `src/Variant/{Domain,Application,Infrastructure}`. Responsible
   for actually generating, storing, and serving resized/re-encoded files. This is the DDD
   rewrite that replaced the bundle's old LiipImagine integration, and it *is* formally
   layered.

## Variant context layering

`deptrac.yaml` enforces this boundary in CI:

```yaml
deptrac:
    paths: [src/Variant]
    layers:
        - name: Domain
          collectors: [{ type: directory, value: src/Variant/Domain/.* }]
        - name: Application
          collectors: [{ type: directory, value: src/Variant/Application/.* }]
        - name: Infrastructure
          collectors: [{ type: directory, value: src/Variant/Infrastructure/.* }]
    ruleset:
        Domain: ~                    # Domain depends on nothing
        Application: [Domain]        # Application may depend on Domain
        Infrastructure: [Application, Domain]  # Infrastructure may depend on both
```

- **`Variant/Domain`** — the model (`Variant`, `VariantId`, `VariantSpec`, `VariantState`,
  `VariantPath`, `SourcePath`, `SourceImage`, `GeneratedImage`, `Dimensions`, `CropBox`,
  `PointOfInterest`, `OutputFormat`, `Quality`), the filter value objects (`Filter`
  interface + `Crop`/`Resize`/`Rotate`/`Thumbnail`/`Background`/`Watermark`/`FilterChain`),
  the *ports* (interfaces: `ImageManipulator`, `PostProcessor`, `SourceReader`,
  `VariantStorage`, `GenerationLock`, `Lock`, `Clock`), small domain services
  (`VariantIdHasher`, `AspectCropCalculator`), and domain events (`VariantRequested`,
  `VariantGenerated`, `VariantGenerationFailed`). Zero framework dependencies — no Symfony,
  no Doctrine (there is no database layer in this bundle at all; all persistent state is
  filesystem/Flysystem-based), nothing outside plain PHP.
- **`Variant/Application`** — orchestration: `Command\GenerateVariant`,
  `Query\{ResolveVariantUrl,ResolvedUrl,PendingFallbackStrategy}`, the two handlers
  (`GenerateVariantHandler`, `ResolveVariantUrlHandler`), application-level ports
  (`GenerationDispatcher`, `DomainEventBus`, `OriginalUrlResolver`, `PendingUrlBuilder`,
  `UrlSigner`), and services (`FilterFactory`, `FilterSetRegistry`, `VariantSpecFactory`,
  `PendingGenerationTracker`). May depend on Domain, nothing else.
- **`Variant/Infrastructure`** — every concrete adapter: `Flysystem\FlysystemVariantStorage`,
  `Intervention\InterventionImageManipulator`, `Lock\{SymfonyGenerationLock,SymfonyLock}`,
  `Messenger\{MessengerGenerationDispatcher,GenerateVariantMessageHandler}`,
  `Sync\SyncGenerationDispatcher`, `Terminate\TerminateGenerationDispatcher`,
  `PostProcess\{Jpegoptim,Pngquant,Cwebp,Avifenc}PostProcessor` (+ `CliPostProcessor`
  base), `Source\ResolverChainSourceReader`,
  `Symfony\{DefaultOriginalUrlResolver,SymfonyDomainEventBus,SymfonyUriSigner,SystemClock}`,
  and `Presentation\{Controller\ImageVariantController, EventListener\ResponseCacheOverrideListener,
  UrlGenerator\{QueryPendingUrlBuilder,VariantResponsiveImageUrlGenerator}}`. May depend on
  Application and Domain.

No Application/Domain code ever imports an Infrastructure class — every interaction crosses
through a port interface, which is exactly what makes each row in the
[Overview's port table](/guide/variant-pipeline/overview#ports-the-extension-seams)
swappable without touching the pipeline itself.

## Domain events

`GenerateVariantHandler` publishes one of `VariantGenerated` or `VariantGenerationFailed`
after every generation attempt (via `DomainEventBus` → `SymfonyDomainEventBus` →
Symfony's `EventDispatcherInterface`). Subscribe to them like any other Symfony event if
you want side effects (metrics, invalidating a downstream cache, alerting) without touching
the pipeline itself.

## Wiring entry point

`Tito10047\ProgressiveImageBundle\DependencyInjection\ProgressiveImageExtension` is the
single place that turns `progressive_image:` config into a service graph. Its
`configureVariantContext()` method only runs the Variant-context wiring
(`configureVariantStorage()`, `configureVariantLock()`, `configureVariantDispatcher()`,
and registering all the handlers/adapters above) when `variant_store.storage` is set —
otherwise the Variant context simply isn't wired into the container at all. Three compiler
passes (`CheckCacheInterfacePass`, `ValidateFilterSetsPass`,
`ValidatePostProcessorBinariesPass`) fail the container compile step for config mistakes
that would otherwise only surface as a runtime error on first request.
