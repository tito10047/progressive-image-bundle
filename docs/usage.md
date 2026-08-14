# <img src="logo/SVG/ImageBundleLogo-01-cropped.svg" width="100" align="center" alt="Logo"> Usage

The main way to use the bundle is via the `<twig:pgi:Image>` Twig component.

## Basic Usage

Simply provide the path to the image and an alternative text.

```twig
<twig:pgi:Image src="images/landscape.jpg" alt="Beautiful landscape" />
```

## Responsive Images using `sizes`

You can define how many grid columns the image should occupy at different breakpoints. This syntax is inspired by Tailwind. When multiple breakpoints are used, the bundle
automatically wraps the image in a `<picture>` element with corresponding `<source>` tags.

```twig
<twig:pgi:Image 
    src="images/hero.jpg" 
    sizes="sm:12 md:6@landscape lg:4@square"
    alt="Responsive hero image" 
/>
```

### Supported formats in `sizes`:

- `6`: 6 columns on all breakpoints.
- `md:6`: 6 columns from the `md` breakpoint.
- `lg:4@landscape`: 4 columns from `lg` with a `landscape` aspect ratio.
- `xxl:[430x370]`: Explicit dimensions in pixels.
- `xl:[100%]@landscape`: 100% container width.

## Point of Interest (PoI)

If you need a specific subject to stay in frame regardless of the target aspect ratio, use the `pointInterest` attribute.

```twig
<twig:pgi:Image 
    src="images/team.jpg" 
    pointInterest="544x320" 
    sizes="sm:12@square md:6@landscape" 
/>
```

The value `544x320` is the **pixel coordinate** of the focal point in the original image — `544` pixels from the left edge and `320` pixels from the top.

**How it works:** the bundle finds the largest region of the original image whose aspect ratio matches the requested output. It centres that region on the focal point (clamping at the edges), crops it, and then scales the result down to the final target size. This means the image is always scaled — never just sliced at original resolution — and the focal point stays as close to the centre of the output as the image edges allow.

## Other Attributes

| Attribute       | Description                                               |
|:----------------|:----------------------------------------------------------|
| `src`           | Path to the image (required).                             |
| `alt`           | Alternative text for SEO and accessibility.               |
| `sizes`         | Responsiveness definition (columns/breakpoints).          |
| `ratio`         | Default aspect ratio for the entire image.                |
| `retina`        | Enable/disable retina for this image (overrides global).  |
| `preload`       | If present, the image is added to preload (LCP).          |
| `pointInterest` | Focal point — pixel coordinates in the original image (e.g., `544x320`). |
| `class`         | CSS classes for the `<img>` tag.                          |
| `context`       | Array of additional data (e.g., filters for LiipImagine). |

## Integration with LiipImagine

If you are using LiipImagine, you can pass the filter name via `context`.

```twig
<twig:pgi:Image 
    src="uploads/photo.png" 
    :context="{ 'filter': 'my_custom_filter' }"
    alt="User photo"
/>
```

## Retina Support

By default, Retina support is enabled if configured globally. You can override it per image:

```twig
<twig:pgi:Image 
    src="images/photo.jpg" 
    sizes="lg:[1024]" 
    :retina="false" 
/>
```
