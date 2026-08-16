# The Twig Component

Every image is rendered through a single [Symfony UX Twig
Component](https://symfony.com/bundles/ux-twig-component), `<twig:pgi:Image>`
(`Tito10047\ProgressiveImageBundle\Twig\Components\Image`, namespace prefix `pgi`).

```twig
<twig:pgi:Image src="{{ asset('images/hero.jpg') }}" alt="Beautiful landscape" />

<twig:pgi:Image
    src="{{ asset('images/hero.jpg') }}"
    sizes="sm:12 md:6@landscape lg:4@square"
    alt="Responsive image"
    preload
    priority="high"
/>
```

## Props

| Prop            | Type      | Default    | Meaning |
|:------------------|:-----------|:-----------|:--------|
| `src`              | `string`   | —          | The logical image path — anything your configured [resolver](/guide/configuration-reference#resolvers) can turn into a real source. |
| `alt`              | `string`\|null | `null` | Passed straight through to the rendered `<img>`. |
| `sizes`            | `string`\|null | `null` | Space-separated breakpoint assignments — see [Sizes syntax](#sizes-syntax) below. Omitting it renders a single, non-responsive `<img>` at the source's natural size. |
| `ratio`            | `string`\|null | `null` | Default aspect ratio applied to every breakpoint that doesn't specify its own via `@ratio`. Either a named ratio from `responsive_strategy.ratios`, or a literal `"W/H"`. |
| `filter`           | `string`\|null | `null` | Name of a `filter_sets` entry to apply — see [Filters, Formats & Quality](/guide/variant-pipeline/filters-formats-and-quality). |
| `context`          | `array`    | `[]`       | Arbitrary per-call config, merged over `filter_sets`/`image_configs` for this one render — see the merge order in [Filters, Formats & Quality](/guide/variant-pipeline/filters-formats-and-quality). |
| `pointInterest`    | `string`\|null | `null` | Pixel coordinates of the subject to keep centered when cropping, as `"X0xY0"` (e.g. `"544x320"`). See [Point of Interest Cropping](/cookbook/point-of-interest-cropping). |
| `retina`           | `bool`\|null | bundle's `retina.enabled` | Set explicitly per-image to override the bundle default. |
| `preload`          | `bool`     | `false`    | Injects a `<link rel="preload" as="image">` for this image via `PreloadCollector` — use for above-the-fold/LCP images. |
| `priority`         | `string`   | `high`     | The preload link's `fetchpriority`/`imagesrcset` priority hint. Only relevant when `preload` is set. |
| `ttl`              | `int`\|null | bundle default | Overrides the fragment-cache TTL for this render, when `image_cache_enabled: true`. |

## Sizes syntax

Each whitespace-separated token in `sizes` is one breakpoint assignment, parsed by
`Tito10047\ProgressiveImageBundle\DTO\BreakpointAssignment::parseSegments()`:

```
<breakpoint>:<width-spec>@<ratio>|<modifier1>|<modifier2>
```

- **`<breakpoint>:`** — a key from `responsive_strategy.grid.layouts` (e.g. `sm`, `md`,
  `lg`, or your custom framework's names). Omit it (just `<width-spec>@<ratio>`) and it
  defaults to `default`.
- **`<width-spec>`** is one of:
  - a bare number — grid **columns** out of `responsive_strategy.grid.columns` (e.g. `12` = full width, `6` = half).
  - `[WxH]` — an explicit pixel size, e.g. `[430x370]`.
  - `[N%]` — a percentage of the viewport, e.g. `[100%]`.
  - `[W]` — an explicit pixel width with no forced height.
- **`@<ratio>`** *(optional)* — a named ratio from `responsive_strategy.ratios` (e.g.
  `@landscape`), a literal ratio (`@16/9`), or `@[WxH]`. If the width-spec already supplied
  both `W` and `H` (`[430x370]`) and no `@ratio` is given, the ratio is derived from that
  pair automatically.
- **`|<modifier>`** *(optional, repeatable)* — a named modifier applied to this breakpoint;
  see [Custom Modifier](/cookbook/custom-modifier).

```twig
sizes="sm:12 md:6@landscape lg:4@square xxl:[430x370] xl:[100%]@landscape lg:4@square|circle"
```

| Token             | Meaning |
|:-------------------|:--------|
| `sm:12`             | Full width (12/12 columns) from `sm` |
| `md:6@landscape`    | Half width from `md`, cropped to the `landscape` ratio |
| `lg:4@square`       | One-third width from `lg`, cropped to `square` |
| `xxl:[430x370]`     | Fixed 430×370px from `xxl` (ratio implied: 430/370) |
| `xl:[100%]@landscape` | Full viewport width from `xl`, cropped to `landscape` |
| `lg:4@square\|circle` | One-third width, `square` ratio, plus the `circle` modifier |

## What gets rendered

`templates/components/Image.html.twig` renders a wrapper `<div>` (Stimulus-controlled,
carrying `--img-width`/`--img-aspect` CSS custom properties reserved up front for zero
CLS) containing:

- a `<canvas>` placeholder, decoded client-side from the image's Blurhash;
- either a `<picture>` with one `<source>` per resolved breakpoint/format (when `sizes`
  produced responsive attributes) or a single `<img>`;
- an error overlay, shown automatically whenever metadata resolution failed (i.e. no
  Blurhash could be computed).

The `<img>` itself is revealed (and the placeholder hidden) once it fires `load`, via the
`progressive-image` Stimulus controller (`assets/controllers/progressive-image_controller.js`).

## Retina / high-density output

When `retina.enabled` (bundle-wide) or the `retina` prop (per-image) is true, every
resolved breakpoint gets additional `srcset` candidates at each configured multiplier
(`retina.multipliers`, default `[1, 2]`) — e.g. a 400px-wide breakpoint also emits a
800px (`2x`) candidate.

## Preloading LCP images

```twig
<twig:pgi:Image src="{{ asset('images/hero.jpg') }}" preload priority="high" alt="Hero" />
```

Set `preload` on your largest-contentful-paint candidate (typically one hero image per
page). It's collected by `PreloadCollector` and rendered as `<link rel="preload">` tags —
wire your base layout to output them in `<head>` via the collector's link provider.

## Generating a URL without the component

`<twig:pgi:Image>` renders a full placeholder/`<picture>`/Stimulus markup block — the right
choice for content in a page template, but not for the many places you just need a plain
URL string: an `<img>` tag you're building by hand, `og:image`/Twitter-card meta tags, a
JSON/API response, a sitemap, an email template. For those, use the `pgi_filter()` Twig
function instead:

```twig
<meta property="og:image" content="{{ pgi_filter('images/hero.jpg', 'og_image') }}">

<img src="{{ pgi_filter('images/hero.jpg', 'thumb_small') }}" alt="Hero">
```

```php
// wherever you build the response
$url = $twig->render('...'); // or inject the Twig Environment / call it from a controller via {{ }}
```

`pgi_filter(path, filterSetName, context = [])` resolves a `filter_sets` entry (the
[same `filter_sets` config the component's `filter` prop uses](/guide/variant-pipeline/filters-formats-and-quality))
into a variant URL. Two differences from the component's own filter-set handling matter:

- **No forced resize.** The component's `sizes`/breakpoint machinery always ends up
  applying its own `thumbnail`/`crop` sizing on top of whatever the filter set defines.
  `pgi_filter()` does not — the filter set's own filters (a `thumbnail`, a plain
  `watermark` with no resize at all, whatever you configured) are applied exactly as
  written, nothing added.
- **No "wait" pending fallback.** If the variant isn't ready yet, `pgi_filter()` always
  returns the original image's URL while generation is triggered in the background — there
  is no page render here to redirect through a signed "wait" endpoint, so
  `generation.fallback_while_pending: wait` has no effect on this function.

See [Migrating from LiipImagineBundle](/guide/migrating-from-liip) for how this maps onto
Liip's `imagine_filter()`.
