# Point of Interest Cropping

By default, a size mismatch between the source and target aspect ratio is resolved with a
centered outbound crop (`Thumbnail::outbound()`). When the interesting part of an image
isn't centered — a face near the edge of a photo, a product off to one side — pass a
**point of interest**: the pixel coordinate that must stay inside the crop.

## Usage

```twig
<twig:pgi:Image
    src="{{ asset('images/team-photo.jpg') }}"
    sizes="sm:12 md:6@square"
    pointInterest="544x320"
    alt="Team photo"
/>
```

`pointInterest` is a string, `"X0xY0"`, in **pixel coordinates of the original image** —
not a percentage, not coordinates relative to any particular breakpoint.

## How it's used

When both `pointInterest` and the original image's dimensions are known,
`VariantSpecFactory::create()` takes a different path than the plain outbound-crop default:

1. `AspectCropCalculator::calculate(poi, target, original)` finds the *largest* region of
   the original that matches the target aspect ratio and is centered on the POI pixel
   (clamped so the crop box never runs outside the original's bounds);
2. a `Crop` filter for that region is applied, **followed by** `Thumbnail::inset(target)` to
   scale the crop down to the exact requested size.

Crop always precedes thumbnail in this path specifically so the point-of-interest crop
can never be silently overridden by a leftover "centered" thumbnail from elsewhere in the
merged filter config — the target size and the POI-driven crop always win.

```php
// AspectCropCalculator, simplified
$cropWidth/$cropHeight = the largest box matching the target ratio that fits in $original;
$startX = clamp($poi->x - $cropWidth / 2, 0, $original->width - $cropWidth);
$startY = clamp($poi->y - $cropHeight / 2, 0, $original->height - $cropHeight);
```

## Getting the pixel coordinates

You need the POI in the *original* image's pixel space. A common pattern is storing it
alongside the upload (e.g. from a face-detection step, or an admin-configurable focal
point picker), then passing it straight through:

```twig
<twig:pgi:Image
    src="{{ asset(product.imagePath) }}"
    pointInterest="{{ product.focalPointX }}x{{ product.focalPointY }}"
    sizes="sm:12 md:6@landscape"
    alt="{{ product.name }}"
/>
```

If you pass a `pointInterest` without the original dimensions being resolvable (e.g. the
source can't be analyzed), the factory simply falls back to the default centered outbound
crop — it never errors out over a missing POI.
