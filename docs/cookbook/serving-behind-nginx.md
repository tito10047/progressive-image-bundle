# Serving Behind Nginx

Once a variant is written to storage, it's meant to be served **directly from disk (or a
CDN in front of it)**, without touching PHP on every request — this is why
`VariantStorage::publicPath()` builds a plain, predictable URL under
`variant_store.public_url_prefix` (default `/media/pgi`) rather than routing through a
controller.

## Serving generated variants statically

If `variant_store.storage` uses a local Flysystem adapter rooted at, say,
`public/media/pgi`, point nginx at that directory directly and let PHP handle only what
isn't already on disk:

```nginx
location /media/pgi/ {
    root /path/to/app/public;
    try_files $uri @php;
    add_header Cache-Control "public, max-age=31536000, immutable";
}

location @php {
    # your normal Symfony front-controller proxy_pass / fastcgi_pass
}
```

Because every stored variant's path is content-addressed (see
[Variant Pipeline → Storage](/guide/variant-pipeline/storage#layout)), it's safe to cache
these responses aggressively and indefinitely — the path itself changes if the source or
spec ever changes, so there's no invalidation to worry about.

`@php` here only needs to reach the normal Symfony front controller — the one route this
bundle adds, `pgi_variant_serve`, lives at the **fixed** path `/media/pgi/wait` (not under
the content-addressed subpaths), so a `try_files` miss on any other `/media/pgi/...` path
simply reaches your app's usual routing/404 handling, not automatic on-the-fly
regeneration. Automatic generation-on-first-request happens through the normal
`ResolveVariantUrlHandler` flow when a page is rendered (see
[Variant Pipeline → Overview](/guide/variant-pipeline/overview#end-to-end-flow)), not by
requesting an arbitrary variant URL directly.

## The `wait` fallback route

If you use `generation.fallback_while_pending: wait`, the `<picture>`/`<img>` markup for a
still-generating image points directly at a **signed** URL under `/media/pgi/wait` (not a
content-addressed path), which nginx should proxy straight to PHP like any other route —
no special `location` block needed beyond your normal front-controller config. See
[Generation Strategies](/guide/variant-pipeline/generation-strategies#serving-the-wait-fallback)
for exactly what that endpoint does: it verifies the signature, generates synchronously if
needed, and always 302-redirects to the real (content-addressed) public path, which then
gets served by the static `location /media/pgi/` block above on the client's next request.

## Cloud storage / CDN

If `variant_store.storage` is an S3 (or similar) Flysystem adapter instead of local disk,
skip the `location /media/pgi/` block entirely — point `variant_store.public_url_prefix`
at your CDN/bucket's own public URL, and let the CDN serve those paths directly. Only the
`pgi_variant_serve` route (for the `wait` fallback, if used) needs to reach your PHP app.
