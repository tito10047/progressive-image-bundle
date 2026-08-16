# On-the-Fly Resolve Route

For most apps, the `<twig:pgi:Image>` component and [`pgi_filter()`](/guide/twig-component#generating-a-url-without-the-component)
are enough — both trigger generation as a side effect of rendering a page. But some
consumers can't render a Twig template at all: a headless frontend hitting the same media
store, another app sharing the same storage, or a straight URL you want to hand out (e.g. a
CDN origin-pull target). For those, `/media/pgi/resolve/{filterSet}/{path}` behaves like
LiipImagineBundle's classic `/media/cache/resolve/<filter>/<path>`: hit it with a GET, it
generates the variant synchronously if needed, and redirects to the result.

```
GET /media/pgi/resolve/thumb_small/uploads/hero.jpg
→ 302 Location: /media/pgi/jpeg/ab/ab12.../uploads/hero.jpg
```

The route is registered automatically wherever `config/routes.php` is imported (the same
place `pgi_variant_serve`, the existing "wait" endpoint, comes from) — no extra config
needed beyond having `variant_store.storage` and at least one `filter_sets` entry
configured.

## How it differs from the `wait` endpoint

| | `pgi_variant_resolve` (this route) | `pgi_variant_serve` (the `wait` endpoint) |
|---|---|---|
| URL shape | `/media/pgi/resolve/{filterSet}/{path}` | `/media/pgi/wait?source=...&width=...&height=...&...` |
| Signed? | No | Yes — every request must carry a valid signature |
| Purpose | A general "give me this filter of this image" URL you can hand out or link to directly | The `fallback_while_pending: wait` redirect target, only ever built internally by `QueryPendingUrlBuilder` |
| Generation | Always synchronous, in-request, regardless of `generation.strategy` | Always synchronous, in-request (same behavior) |

Both always redirect and never stream bytes themselves — the next hit goes straight to the
storage's own public URL (local disk served by nginx, or directly from S3/CDN) without
touching PHP again, exactly like every other variant in this bundle.

## Why it's safe to leave unsigned

The `wait` endpoint requires a signature because its query string is fully attacker-shaped
— an unsigned version would let anyone request generation of arbitrary (width, height,
filter, context) combinations, an easy way to force expensive, uncached generation work.
This route avoids that by construction: `{filterSet}` must be the name of an entry you
already defined in `filter_sets` (`VariantSpecFactory` rejects anything else with
`InvalidFilterDefinition`), so a request can never do anything other than apply a filter set
you already chose to expose, on whatever source path it names. That's the same trust
boundary LiipImagineBundle's own resolve route has always had.

`{path}` itself is still caller-controlled, so generation *can* be triggered for any path —
put this route behind the same caching layer (nginx/CDN) you'd put any other public
endpoint behind if that's a concern for your traffic profile; once a given
`(filterSet, path)` pair has been generated once, subsequent requests are storage hits and
cost nothing beyond a redirect.
