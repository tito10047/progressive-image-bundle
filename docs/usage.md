# Usage

The main way to use the bundle is via the `<twig:pgi:Image>` Twig component.

## Basic Usage

Simply provide the path to the image and an alternative text.

```twig
<twig:pgi:Image src="images/landscape.jpg" alt="Beautiful landscape" />
```

## Responsive Images using `sizes`

You can define how many grid columns the image should occupy at different breakpoints. This syntax is inspired by Tailwind.

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

If you need a specific object to always remain visible when cropping, use the `pointInterest` attribute.

```twig
<twig:pgi:Image 
    src="images/team.jpg" 
    pointInterest="75x25" 
    sizes="sm:12@square md:6@landscape" 
/>
```

*The value `75x25` means 75% width and 25% height from the top-left corner.*

## Other Attributes

| Attribute       | Description                                               |
|:----------------|:----------------------------------------------------------|
| `src`           | Path to the image (required).                             |
| `alt`           | Alternative text for SEO and accessibility.               |
| `sizes`         | Responsiveness definition (columns/breakpoints).          |
| `ratio`         | Default aspect ratio for the entire image.                |
| `preload`       | If present, the image is added to preload (LCP).          |
| `pointInterest` | Focal point for cropping (e.g., `50x50`).                 |
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
