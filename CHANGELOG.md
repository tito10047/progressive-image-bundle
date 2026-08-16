# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0-rc1] - 2026-08-16 [BC BREAK]

A from-scratch DDD rewrite of the Variant pipeline. First public release candidate —
expect further breaking changes before a final `2.0.0` tag.

### Added

- New **Variant pipeline**: content-addressed, deterministically-hashed image variants
  generated through a layered DDD core (Domain/Application/Infrastructure/Presentation),
  replacing the old runtime-only responsive attribute generator. Every generated size is
  identified by an HMAC hash of source + spec, so the same request from any worker
  produces the same file with no coordination needed.
- Three generation strategies — `sync`, `terminate` (`kernel.terminate`), and `async`
  (Messenger) — configurable via `generation.strategy`.
- `pgi_filter()` Twig function: resolve a `filter_sets` entry to a plain URL string
  outside `<twig:pgi:Image>` (API responses, `og:image`, sitemaps, emails). Also callable
  directly from PHP via `ResolveFilterUrlHandler` — see
  [The Twig Component → Resolving a URL from PHP](https://pgi.blue/guide/twig-component#resolving-a-url-from-php-a-controller-no-twig-at-all).
- On-the-fly HTTP resolve route (`/media/pgi/resolve/{filterSet}/{path}`), Liip-style —
  generates a variant synchronously on first `GET` and redirects to the result.
- `progressive-image:variant:warm` and `progressive-image:variant:remove` CLI commands.
- New filters: `grayscale`, `negative`, `auto_rotate`, `paste`, `relative_resize`.
- `progressive` (progressive JPEG / interlaced PNG) and `strip_metadata` encoding options,
  AVIF/WebP encoding, and format negotiation.
- Real post-processing: `jpegoptim`, `pngquant`, `cwebp`, `avifenc`.
- Optional remote/HTTP source loading (`variant_source.http`), off by default and
  allowlist-only (it's an SSRF surface).
- `FilterModifierInterface` is now actually wired up — was a documented no-op in 1.x.
- SVG sources are detected and served as-is, never rasterized through the Variant pipeline.
- `VariantStorage::list()` and `FilterSetRegistry::names()`.
- [Symfony Flex recipe](https://github.com/symfony/recipes-contrib/pull/2027) submitted —
  zero-config `composer require`, including an AssetMapper resolver chain and automatic
  responsive-stylesheet generation.

### Changed

- Documentation rebuilt around [pgi.blue](https://pgi.blue/), including a
  [migration guide from LiipImagineBundle](https://pgi.blue/guide/migrating-from-liip).

### Removed

- **LiipImagineBundle dependency purged entirely.** The bundle no longer requires or
  integrates with `liip/imagine-bundle` in any way.

### Fixed

- `asset_mapper` resolver no longer breaks container compilation on projects without
  `symfony/asset-mapper` installed — the DI reference is now optional, with a clear
  `LogicException` thrown on first actual use instead of a compile-time failure.
- The bundle's AssetMapper-registered assets now use the correct scoped npm namespace
  (`@tito10047/progressive-image-bundle`), fixing the `style.css` autoimport that silently
  never fired before.
- `generation.transport` is now actually wired into Messenger routing for the `async`
  strategy — previously ignored, silently falling back to synchronous in-process handling.
- Dozens of correctness and hardening fixes across the Variant pipeline surfaced during the
  rewrite's own code review (locking, caching, path encoding, orphaned tmp-file cleanup,
  point-of-interest cropping, preload headers) — see the git history for the full list.

### Requirements

- **PHP 8.3+** (was 8.2+) — required by `intervention/image` `^4.2`, which the Variant
  pipeline is built on.
- **Symfony 6.4, 7.4, or 8.1+**.

## [1.2.0] - 2026-08-14

### Fixed

- **Point of Interest (PoI) cropping now uses pixel coordinates**, not percentages. The `pointInterest` attribute (e.g. `pointInterest="544x320"`) is interpreted as the absolute pixel position of the focal point in the original image.
- **PoI cropping now scales the image instead of cutting at original resolution.** The bundle finds the largest region in the original image whose aspect ratio matches the requested output, centres that region on the focal point (clamping at edges), and scales the result down to the target size. Previously the exact target size was cut from the original without any scaling, which produced crops at full original resolution and often placed the focal point at the wrong position.

## [1.1.0] - 2026-01-17 [BC BREAK]

### Changed

- Changed responsive image rendering to use the `<picture>` element with multiple `<source>` tags instead of a single `<img>` with combined `srcset`.
- **BC Break:** `ResponsiveAttributeGenerator::generate()` now returns `ResponsiveAttributesInterface` instead of an `array`.
- Updated Twig component `Image` to support the new `<picture>` structure.
- Added support for custom modifiers in responsive selectors (e.g., `lg:4|circle`).

### Added

- Added `ResponsiveAttributesInterface`, `ResponsiveSourceInterface` and their default implementations.
- Added `DefaultResponsiveImageUrlGenerator` as a fallback when `LiipImagineBundle` is not available.

## [1.0.3] - 2026-01-14

### Added

- Added Symfony command `progressive-image:generate-custom-css` to generate CSS for custom frameworks.

