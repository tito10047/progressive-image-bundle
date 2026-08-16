# Variant Pipeline — Overview

The **Variant pipeline** is what actually generates resized/re-encoded image files. It
replaces the bundle's old LiipImagine integration entirely — there is no LiipImagine
dependency anywhere in this codebase anymore.

It only activates once you set `variant_store.storage` to a
`League\Flysystem\FilesystemOperator` service (see
[Storage](/guide/variant-pipeline/storage)). Without it, the bundle still computes
responsive attributes and dimensions, but never writes a resized file — `src` is passed
through unchanged.

## Core concepts

**Variant** — the aggregate representing one generated (or about-to-be-generated) image:
a specific `(source, spec)` pair, with a lifecycle `Requested → Generating → Ready` or
`→ Failed`.

**VariantSpec** — `{filters: FilterChain, format: OutputFormat, quality: Quality}`. Two
specs that only differ in a setting with no actual effect (e.g. `quality` on a `png`,
which the encoder ignores) normalize to the same canonical form, so they don't produce
duplicate stored files.

**VariantId** — a deterministic, **content-addressed** identifier computed by
`VariantIdHasher`: an HMAC-SHA256 (keyed by `secret`, falling back to `%kernel.secret%`)
over a canonical JSON payload of `{src, spec, v: 1}`, base64url-encoded. Two independent
requests for the same source + spec — from any request, any worker, at any time — produce
the *exact same* id. This is what makes generation safe with no coordination beyond a
short-lived lock: there's nothing to deduplicate across processes, because the id already
is the deduplication key.

**VariantPath** — the single source of truth for where a variant lives, both in storage
and in its public URL:

```
{format}/{first 2 chars of id}/{full id}/{source-path}.{ext}
```

e.g. `webp/ab/abCdEf.../uploads/hero.jpg.webp`. The 2-character shard prefix keeps any one
storage directory from accumulating too many entries.

## End-to-end flow

```
Twig <twig:pgi:Image> render
        │
        ▼
ResolveVariantUrlHandler (hot path, runs on every render)
        │  1. VariantSpecFactory::create() → VariantSpec
        │  2. Variant::request(source, spec) → content-addressed VariantId
        │  3. VariantStorage::exists(path)?
        │
        ├── yes ──────────────────────────► return the real public URL
        │
        └── no
             │  4. GenerationDispatcher::dispatch(GenerateVariant)  ── strategy-dependent, see below
             │  5. re-check exists() (sync may have just finished)
             │
             ├── now exists ───────────────► return the real public URL
             │
             └── still pending
                  │ mark pending (forces this response to no-store — see Caching)
                  ▼
                  return a fallback URL: the original image, or a signed
                  "/media/pgi/wait" URL, per generation.fallback_while_pending
```

`ResolveVariantUrlHandler` is the hot path — it must run on every render. It's registered
`setShared(false)` and memoizes per `VariantId` for its own (per-request) lifetime, so
rendering the same image twice on one page only resolves it once.

**Generation itself** (reading the source, applying filters, encoding, post-processing,
storing) is a single code path shared by all three strategies —
`GenerateVariantHandler::__invoke()`:

```
GenerateVariantHandler
  1. acquire a GenerationLock keyed by VariantId (no-op / returns silently if another
     process already holds it — that process will finish the work)
  2. re-check storage->exists() and a fresh fail-marker inside the lock
  3. Variant::startGenerating()
  4. SourceReader::read(source) → SourceImage
  5. ImageManipulator::process(source, spec) → GeneratedImage
  6. run every matching PostProcessor (tag: progressive_image.variant.post_processor)
  7. VariantStorage::write(path, image)
  8. publish VariantGenerated (or, on failure: write a fail marker + publish
     VariantGenerationFailed, then rethrow)
```

See [Generation Strategies](/guide/variant-pipeline/generation-strategies) for *when* this
runs (sync/terminate/async), and [Filters, Formats & Quality](/guide/variant-pipeline/filters-formats-and-quality)
for how a `VariantSpec` is actually built from config + Twig `context`.

## Serving

Once a variant exists in storage, it's served **directly from storage's own public URL**
(e.g. by nginx/a CDN reading straight from the Flysystem-backed public directory) — no PHP
involved. `ImageVariantController::serve()` (route `pgi_variant_serve`, `/media/pgi/wait`)
only exists for two other cases: the "wait" pending-fallback's signed URL, and an nginx
`try_files` miss target for a path that existed but got evicted. It always 302-redirects,
never streams bytes — see [Generation Strategies](/guide/variant-pipeline/generation-strategies#serving-the-wait-fallback).

## Ports (the extension seams)

| Port (`Variant\Domain\Port\*` / `Variant\Application\Port\*`) | Responsibility | Built-in adapter |
|:---|:---|:---|
| `ImageManipulator` | Apply filters, encode | `InterventionImageManipulator` |
| `PostProcessor` (tagged, `iterable`) | Optional CLI re-encode/optimize | `Jpegoptim`/`Pngquant`/`Cwebp`/`Avifenc` PostProcessor |
| `VariantStorage` | Persist/read generated files | `FlysystemVariantStorage` |
| `SourceReader` | Load the original image | `ResolverChainSourceReader` |
| `GenerationLock` / `Lock` | Prevent duplicate concurrent generation | `SymfonyGenerationLock` (symfony/lock) |
| `Clock` | Testable time | `SystemClock` |
| `GenerationDispatcher` | *When* generation runs | `Sync`/`Terminate`/`MessengerGenerationDispatcher` |
| `OriginalUrlResolver` | Fallback URL while pending | `DefaultOriginalUrlResolver` |
| `PendingUrlBuilder` | Builds the "wait" URL | `QueryPendingUrlBuilder` |
| `UrlSigner` | Signs/verifies the "wait" URL | `SymfonyUriSigner` |
| `DomainEventBus` | Publishes `VariantRequested`/`Generated`/`GenerationFailed` | `SymfonyDomainEventBus` (Symfony EventDispatcher) |

Every one of these is a normal Symfony service alias — see the [Cookbook](/cookbook/custom-storage-backend)
for worked examples of swapping each.
