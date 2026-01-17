# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] - 2026-01-17

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

