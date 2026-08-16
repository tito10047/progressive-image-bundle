# Storage

The **Variant Store** — where generated files actually live — is deliberately never
called a "cache" in this codebase: cache means the HTTP/fragment cache (see
[Caching](/guide/caching)), a different concern entirely. A variant, once written, is
treated as a durable artifact you can regenerate on demand, not an ephemeral cache entry.

## Configuring it

```yaml
progressive_image:
    variant_store:
        storage: 'oneup_flysystem.variants_filesystem' # any League\Flysystem\FilesystemOperator service
        prefix: ''
        public_url_prefix: '/media/pgi'
        fail_marker_ttl: 300
```

`variant_store.storage` is a service id resolving to any
`League\Flysystem\FilesystemOperator` — local disk, S3, or anything else Flysystem
supports. **This is the only switch you need to move between local and cloud storage** —
there's a single `VariantStorage` implementation, `FlysystemVariantStorage`, and it works
identically for either, because the whole storage strategy lives in *which* Flysystem
adapter you wire up, not in a different PHP class.

```yaml
# requires oneup/flysystem-bundle
oneup_flysystem:
    adapters:
        variants.adapter:
            local:
                directory: '%kernel.project_dir%/public/media/pgi'
    filesystems:
        variants_filesystem:
            adapter: variants.adapter
```

Leaving `variant_store.storage` unset keeps the bundle in "legacy" mode: no generation
ever happens, and `<twig:pgi:Image>` only computes responsive attributes for the source
path as-is.

## Layout

Every path is built by `VariantPath::for(id, source, format)` — the single source of
truth used consistently by storage, the serving controller, and the URL generator:

```
{format}/{first 2 chars of id}/{full id}/{source-path}.{ext}
```

e.g. a webp variant of `uploads/hero.jpg` might live at
`webp/ab/abCdEf0123.../uploads/hero.jpg.webp`. The format-first layout means deleting an
entire format's worth of variants (e.g. after dropping `avif` support) is one directory
delete; the 2-character shard prevents any single directory from accumulating unbounded
entries as more sources/specs get generated.

`FlysystemVariantStorage::publicPath()` percent-encodes each path segment and prefixes it
with `variant_store.public_url_prefix` — that's the URL your web server or CDN needs to be
able to serve directly from the underlying storage, without touching PHP.

## Fail markers

If generation throws, `GenerateVariantHandler` writes a sibling `<path>.failed` file
containing a Unix timestamp instead of the image itself. While that marker is "fresh"
(younger than `variant_store.fail_marker_ttl` seconds, default 300), further generation
attempts for the same `VariantPath` short-circuit immediately instead of retrying a source
that's currently broken — this throttles repeated failures (e.g. a temporarily-unreachable
remote source) without needing a separate circuit breaker. A corrupted or empty marker file
is treated as "no marker" rather than silently parsed as epoch `0`, so throttling can never
get permanently stuck on.

## Atomic writes

Both the variant itself and its fail marker are written atomically: bytes go to a random
`<path>.tmp-<random>` name first, then `move()`d into place, so a concurrent reader never
sees a partially-written file. If the move fails, the orphaned temp file is cleaned up on a
best-effort basis (and the cleanup failure itself is logged, never silently swallowed)
before the original error is rethrown.

## Implementing your own `VariantStorage`

You normally don't need to — swap the underlying `FilesystemOperator` instead. If you
genuinely need a non-Flysystem backend, see [Custom Storage Backend](/cookbook/custom-storage-backend),
which also covers the shared `VariantStorageContractTest` suite every implementation
(including the built-in one) is verified against.
