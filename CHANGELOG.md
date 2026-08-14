# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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

