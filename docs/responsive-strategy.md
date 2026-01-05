# <img src="logo/SVG/ImageBundleLogo-01-cropped.svg" width="100" align="center" alt="Logo"> Responsive Strategy

Progressive Image Bundle doesn't just use static dimensions; it operates with a **Breakpoint-First approach**. This means it calculates the exact width of the image based
on your CSS grid.

## 1. Grid Definition

In the configuration, you can choose one of the predefined frameworks or define your own.

```yaml
progressive_image:
    responsive_strategy:
        grid:
            framework: bootstrap # or 'tailwind'
```

### Supported Frameworks:

- **bootstrap**: 12 columns, breakpoints: `xs`, `sm`, `md`, `lg`, `xl`, `xxl`.
- **tailwind**: 12 columns, breakpoints: `default`, `sm`, `md`, `lg`, `xl`, `2xl`.
- **custom**: Here you define your own columns and container dimensions.

### Custom Grid (Custom)

```yaml
progressive_image:
    responsive_strategy:
        grid:
            framework: custom
            columns: 12
            gutter: 24
            layouts:
                xl: { min_viewport: 1200, max_container: 1140 }
                md: { min_viewport: 768, max_container: 720 }
                sm: { min_viewport: 0, max_container: null } # null = 100vw
```

## 2. Aspect Ratios (Ratios)

You can define global names for aspect ratios, which you then use in Twig templates.

```yaml
progressive_image:
    responsive_strategy:
        ratios:
            landscape: "16/9"
            portrait: "3/4"
            square: "1/1"
            hero: "21/9"
```

## 3. Upscaling Protection

One of the key features of the bundle is that it **never generates an image larger than its original**.

### Example:

If your grid calculates that for a given breakpoint you need an image with a width of **1200px**, but your source file is only **1000px**:

1. The bundle automatically filters out all variants in `srcset` that would be larger than 1000px.
2. The browser will thus never attempt to download an artificially enlarged (and therefore blurred) image.
3. CPU resources and storage are saved.

## 4. Zero CLS (Cumulative Layout Shift)

The bundle automatically retrieves image dimensions using PHP streams and inserts them into the HTML. This ensures the browser reserves the exact space for the image
before it's even downloaded. As a result, page content doesn't "jump" after the image loads.
