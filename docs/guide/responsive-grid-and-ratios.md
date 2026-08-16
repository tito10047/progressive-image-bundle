# Responsive Grid & Ratios

Progressive Image Bundle uses a **breakpoint-first** approach: instead of storing fixed
image sizes, it calculates the exact pixel width needed at each breakpoint from your CSS
grid, and generates (or requests) exactly that.

## Grid frameworks

```yaml
progressive_image:
    responsive_strategy:
        grid:
            framework: bootstrap # or 'tailwind', or 'custom'
```

- **`bootstrap`** — 12 columns, breakpoints `xs`/`sm`/`md`/`lg`/`xl`/`xxl` with Bootstrap's
  real `min_viewport`/`max_container` values (e.g. `lg`: 992px viewport / 960px container).
- **`tailwind`** — 12 columns, breakpoints `xs`/`sm`/`md`/`lg`/`xl`/`2xl` with Tailwind's
  real breakpoints (e.g. `lg`: 1024px).
- **`custom`** *(default)* — define your own `columns`, `gutter`, and `layouts`.

Any key you set explicitly under `grid` (e.g. a custom `layouts.lg.max_container`) wins
over the framework preset — the preset only fills in what you didn't specify.

### Custom grid

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
                sm: { min_viewport: 0, max_container: null } # null = 100vw (fluid)
```

`max_container: null` means the breakpoint is fluid — its effective width is `100vw`
(capped by `responsive_strategy.fluid_max_width` when estimating a concrete pixel value).

## Aspect ratios

```yaml
progressive_image:
    responsive_strategy:
        ratios:
            landscape: '16/9'
            portrait: '3/4'
            square: '1/1'
            hero: '21/9'
```

Reference these by name in `sizes` (`md:6@landscape`) or as a component-level default via
the `ratio` prop — see [Sizes syntax](/guide/twig-component#sizes-syntax).

## Generating the CSS

`responsive_strategy.grid` also drives the CSS custom properties that lock in each
breakpoint's width/aspect ratio client-side, avoiding layout shift:

```bash
php bin/console progressive-image:generate-custom-css
```

Emits `@layer vendor { .progressive-image-container { ... } }` rules with per-breakpoint
media queries, using `--img-width-<bp>` / `--img-aspect-<bp>` custom properties (with
`var()` fallback chains). Re-run it whenever breakpoints change, and import the generated
file from your JS entry point. Bootstrap/Tailwind users can instead import the pre-built
`assets/styles/progressive-image-{tailwind,bootstrap}.css` shipped with the bundle.

## Upscaling protection

The bundle never generates (or requests) an image wider than its own source. If a
breakpoint's computed target width exceeds the original image's width, that `srcset`
candidate is capped at the original width instead of being upscaled — so the browser never
downloads an artificially enlarged, blurry image, and no storage/CPU is wasted generating
one.

## Zero CLS

Source dimensions are read via PHP streams (no full image load needed) and written into
`--img-width` / `--img-aspect` CSS custom properties on the wrapping `<div>` before the
`<img>` itself is even requested — the browser reserves the correct space up front, so
nothing jumps when the image finishes loading.
