# Generation Strategies

`generation.strategy` decides **when** `GenerateVariantHandler` actually runs. All three
strategies call the exact same handler — only the timing differs.

```yaml
progressive_image:
    generation:
        strategy: async # async (default) | sync | terminate
        transport: async_images # only used when strategy: async
        fallback_while_pending: original # original (default) | wait
        lock_store: null # Symfony Lock DSN; defaults to a FlockStore
```

## `async` (default)

Dispatches `GenerateVariant` onto a Symfony Messenger bus, routed to the transport named
by `generation.transport` (default `async_images`). The bundle wires this for you —
`ProgressiveImageExtension::prepend()` sets
`framework.messenger.routing[GenerateVariant::class] = <transport>` automatically when
`strategy: async`. **Without that wiring, Messenger's default bus would fall back to
handling the message synchronously in-process, silently making "async" behave like
"sync"** — this is exactly the bug the auto-wiring exists to prevent, so you should never
need to configure that routing entry yourself.

Requires `symfony/messenger` — the container fails to compile with a clear `LogicException`
if it's not installed. `MessengerGenerationDispatcher` deduplicates only *within the
current request* (`$dispatchedThisRequest`); cross-request/cross-worker safety comes from
the `GenerationLock` + storage-exists idempotency check inside `GenerateVariantHandler`
itself — a worst case is a harmless duplicate no-op job, never a correctness problem.

A worker consumes the transport and invokes `GenerateVariantMessageHandler`, which just
calls `GenerateVariantHandler`. See [Async Worker Setup](/cookbook/async-worker-setup).

## `sync`

Runs generation **inline, in the request**, via `SyncGenerationDispatcher`. Any exception
is caught and logged (PSR-3 if available, `error_log()` otherwise) — a broken source must
never crash the whole page render; it falls back to the pending strategy instead.

Because generation completes before `dispatch()` returns, `ResolveVariantUrlHandler`
re-checks `storage->exists()` immediately after dispatching — so **even the very first
request for a brand-new image gets served the real generated file**, not the fallback.

Good for local development, low-traffic sites, or CI/tests — no worker process needed.

## `terminate`

Queues commands in-memory during the request, then flushes them from a
`kernel.terminate` listener (`TerminateGenerationDispatcher::onTerminate()`) — the response
has already been sent to the browser by then, so generation doesn't add to perceived
latency, and no message broker is required.

`TerminateGenerationDispatcher` is registered **shared** (not `setShared(false)`)
deliberately: it's resolved through two different paths in the same request — the
`GenerationDispatcher` alias (wherever `dispatch()` is called) and the
`kernel.event_listener` tag (which resolves it again for `onTerminate()`). A non-shared
registration would give those two paths different instances, so `onTerminate()` would flush
an *empty* queue instead of the one `dispatch()` actually populated.

On a classic PHP-FPM deployment the container doesn't outlive one request, so this is moot.
On a persistent worker runtime (Swoole/RoadRunner/FrankenPHP), `onTerminate()`
unconditionally empties its queue as its first action on every `kernel.terminate`, so state
never leaks past a request's own termination — the one accepted quirk is that if two
requests interleave before either terminates, a command queued by request B could get
flushed during request A's termination. Since generation is idempotent (content-addressed
id, storage-exists check), this only means slightly-earlier-than-expected generation, never
a lost or duplicated variant.

## `fallback_while_pending`

What `ResolveVariantUrlHandler` returns while a variant is dispatched but not yet ready:

- **`original`** *(default)* — the source's own public URL, via `OriginalUrlResolver`. The
  page shows the unresized original until generation catches up.
- **`wait`** — a signed URL to the `pgi_variant_serve` route
  (`QueryPendingUrlBuilder` + `UrlSigner::sign()`), encoding the exact query parameters
  needed to rebuild the same `VariantSpec`. Hitting it synchronously generates the variant
  (if not already done) and redirects — so the *next* request goes straight to storage.
  Requires `PendingUrlBuilder` to be wired, which it always is once `variant_store.storage`
  is configured.

## Serving the "wait" fallback

`ImageVariantController::serve()` (`GET /media/pgi/wait`, route `pgi_variant_serve`) is the
target for `fallback_while_pending: wait`, and doubles as an nginx `try_files` miss target
(see [Serving Behind Nginx](/cookbook/serving-behind-nginx)). It:

1. verifies the URL's signature (`UrlSigner::check()`) — an unsigned/tampered request gets
   a 404, which is what keeps this endpoint from being an open "generate anything" oracle;
2. rebuilds the exact same `VariantSpec`/`Variant` from query parameters
   (`source`, `width`, `height`, `filterSet`, `poiX`/`poiY`, `origW`/`origH`, `context` as
   JSON) — the same `VariantSpecFactory::create()` call used everywhere else, guaranteeing
   an identical `VariantId`;
3. runs `GenerateVariantHandler` synchronously if the variant doesn't already exist;
4. **always redirects** (`302`, `Cache-Control: no-store, must-revalidate`) — to the
   variant's public path on success, or to the original image on failure. It never streams
   image bytes itself, so the behavior is identical whether storage is local disk behind
   nginx or S3 behind a CDN.

## Locking

Every strategy acquires a `GenerationLock` (keyed by `VariantId`, backed by
`symfony/lock`) inside `GenerateVariantHandler` before doing any work — if another process
already holds it, `acquire()` returns `null` and this call returns immediately, trusting
the lock holder to finish. Configure the backing store with `generation.lock_store` (any
[Symfony Lock DSN](https://symfony.com/doc/current/lock.html)); unset, it defaults to a
`FlockStore` under `%kernel.cache_dir%/pgi-locks`, which is fine for a single machine but
**not** for a multi-server deployment — set a real DSN (e.g. Redis) if you run more than
one app server or worker host.
