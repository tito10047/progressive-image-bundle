# Migrating from LiipImagineBundle

Progressive Image Bundle isn't a drop-in replacement API-wise, but every workflow you
rely on with Liip has a direct equivalent. This page maps the two side by side.

## URL generation

| Liip | PGI | Notes |
|:--|:--|:--|
| `imagine_filter(path, filter)` | `pgi_filter(path, filterSetName)` | Both are plain Twig functions returning a URL string — usable in an `img` `src`, `og:image`, JSON, sitemaps, emails. See [Generating a URL without the component](/guide/twig-component#generating-a-url-without-the-component). |
| an `img` tag built around `imagine_filter(...)` | the `pgi:Image` component | For content inside a page template, prefer the component — it also gives you the placeholder, `picture`/`srcset`, and zero-CLS sizing for free. |

## On-the-fly HTTP resolution

| Liip | PGI |
|:--|:--|
| `/media/cache/resolve/<filter>/<path>` | `/media/pgi/resolve/{filterSet}/{path}` |

Same shape, same purpose: hit it with a GET, it generates the variant synchronously if
needed and redirects to the result. See [On-the-Fly Resolve Route](/cookbook/on-the-fly-resolve-route).

## CLI cache management

| Liip | PGI |
|:--|:--|
| `liip:imagine:cache:resolve` | `progressive-image:variant:warm` |
| `liip:imagine:cache:remove` | `progressive-image:variant:remove` |

See [CLI Commands](/guide/variant-pipeline/cli-commands).

## Filter mapping

Filters are configured under `filter_sets` instead of Liip's `filter_sets` key of the same
name — the config shape is close enough that most entries only need their filter names
translated:

| Liip filter | PGI filter | Notes |
|:--|:--|:--|
| `thumbnail` | `thumbnail` | Same `mode: inset\|outbound` semantics. |
| `crop` | `crop` | Same `size`/`start` shape. |
| `upscale` | *(none — built in)* | PGI never enlarges past the original; there's nothing to configure. |
| `relative_resize` | `relative_resize` | PGI's variant works in percentages (`width_percent`/`height_percent`) rather than Liip's absolute `widen`/`heighten`/`increase` — check [Filters, Formats & Quality](/guide/variant-pipeline/filters-formats-and-quality). |
| `rotate` | `rotate` | Same. |
| `auto_rotate` | `auto_rotate` | Same — reads EXIF orientation, then discards the tag. |
| `background` | `background` | Same. |
| `watermark` | `watermark` | Same. |
| `paste` | `paste` | Same. |
| `grayscale` | `grayscale` | Same. |
| `negative` | `negative` | Same. |
| `interlace` | `formats.progressive: true` | Not a filter — a `formats` (or per-filter-set/context `progressive`) flag: progressive-scan JPEG or Adam7-interlaced PNG. See [Filters, Formats & Quality](/guide/variant-pipeline/filters-formats-and-quality). |
| `strip` | `formats.strip_metadata: true` | Same shape as `progressive` — a `formats` (or per-filter-set/context `strip_metadata`) flag, not a filter. JPEG/WebP/AVIF only. |

Full filter reference, including options: [Filters, Formats & Quality](/guide/variant-pipeline/filters-formats-and-quality).

## Loaders

PGI resolves local sources from `filesystem`, `asset_mapper`, or a `chain` of the two
(`progressive_image.resolvers`). For sources living on S3 or behind a remote URL, enable
`variant_source.http` — see [Remote (HTTP) Source Loading](/cookbook/http-source-loader) —
which lets a `SourcePath` be an absolute `http(s)://` URL instead of a local path.
