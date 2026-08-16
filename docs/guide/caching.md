# Caching

There are two, deliberately separate, caching concerns in this bundle. Don't confuse
them: the **Variant Store** ([Storage](/guide/variant-pipeline/storage)) holds the
*generated image files themselves* and is never called a cache in this codebase. This
page is about the other one — caching the *rendered HTML* of `<twig:pgi:Image>`.

## Enabling fragment caching

```yaml
progressive_image:
    image_cache_enabled: true
    image_cache_service: 'cache.my_cache' # must be a tag-aware pool
    ttl: 3600
```

```yaml
# config/packages/framework.yaml
framework:
    cache:
        pools:
            cache.my_cache:
                tags: true
```

`image_cache_service` must resolve to a `Symfony\Contracts\Cache\TagAwareCacheInterface`
(or carry the `cache.taggable` tag) — this is checked **at compile time**
(`CheckCacheInterfacePass`), so a misconfigured pool breaks `cache:clear`, not a live
request.

## How it works

`TransparentImageCacheSubscriber` hooks into Symfony UX Twig Component's own render
lifecycle (`PreCreateForRenderEvent`/`PreRenderEvent`) and transparently wraps **every**
`pgi:Image` render, without any change to your templates:

1. **`onPreCreate`** — computes a cache key from an md5 hash of the component's raw input
   props (`src`, `sizes`, `filter`, `context`, ...) and checks the cache. On a hit, the
   cached HTML is used directly and the component never even mounts.
2. **`onPreRender`** (only reached on a miss) — swaps the template for
   `templates/cache_wrapper.html.twig`, which includes the real component output through
   the `pgi_cache_save` Twig filter (`TransparentCacheExtension`). That filter stores the
   rendered HTML under the same key, tagged `pgi_tag_<md5(src)>` — so invalidating a
   specific source's cached HTML is `cache.my_cache->invalidateTags(['pgi_tag_'.md5($src)])`.

Both stages key off the component's *raw input props* (not post-mount defaults), so two
images with the same effective config but props supplied in a different order still hit
the same cache key deterministically (props are always the same associative structure).

## Pending variants are never cached

If a render dispatched a variant generation that's still pending
(`PendingGenerationTracker::hasPending()`), **both** cache paths skip it:

- `pgi_cache_save` returns the content unsaved — caching now would freeze the
  original-image fallback into the fragment cache long after the real variant becomes
  ready.
- `ResponseCacheOverrideListener` (a `kernel.response` listener at priority `-1024`, so it
  runs *after* Symfony's own `#[Cache]` attribute listener and wins) forces the whole HTTP
  response to `Cache-Control: no-store, no-cache, must-revalidate, private, max-age=0` +
  `Surrogate-Control: no-store`, and strips `ETag`/`Last-Modified`/`Expires` — regardless of
  what your controller or `#[Cache]` attribute said. This protects browser caches, reverse
  proxies, and CDNs alike from caching a page that's currently showing a stand-in image.

This is exactly why [Storage](/guide/variant-pipeline/storage) and this page are separate
concepts: a "pending" state only exists in the Variant pipeline, but it needs to reach
into *this* HTML/HTTP caching layer to avoid freezing a stale render.
