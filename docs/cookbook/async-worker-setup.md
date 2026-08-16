# Async Worker Setup

`generation.strategy: async` (the default) dispatches `GenerateVariant` commands onto a
Symfony Messenger transport, `generation.transport` (default `async_images`). Nothing
generates a variant until a worker actually consumes that transport — see
[Generation Strategies](/guide/variant-pipeline/generation-strategies#async-default) for
how the routing gets wired automatically.

## 1. Configure the transport

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        transports:
            async_images: '%env(MESSENGER_TRANSPORT_DSN)%'
```

Any Messenger-supported transport works — Doctrine (a `messenger_messages` table),
Redis, Amazon SQS, etc. You don't need to add a `routing` entry yourself: the bundle's
`ProgressiveImageExtension::prepend()` sets
`framework.messenger.routing[GenerateVariant::class] = <generation.transport>` for you
whenever `generation.strategy: async`.

## 2. Run a worker

```bash
php bin/console messenger:consume async_images -vv
```

In production, run this under a process supervisor (systemd, Supervisor, Kubernetes) so it
restarts automatically — Messenger workers exit periodically by design (memory limits,
`--limit`, `--time-limit`), and your supervisor is what keeps one running continuously.

```ini
; /etc/supervisor/conf.d/pgi-worker.conf
[program:pgi-worker]
command=php /path/to/app/bin/console messenger:consume async_images --time-limit=3600
numprocs=2
autostart=true
autorestart=true
user=www-data
```

## 3. What happens while no worker is running

Nothing breaks. `ResolveVariantUrlHandler` still dispatches the message and immediately
falls back per `generation.fallback_while_pending` (the original image by default) — pages
keep working, just without the resized variant, until a worker picks up the queue. This is
also exactly what makes `sync`/`terminate` reasonable choices for environments where
running a separate worker process isn't practical (small deployments, some serverless
platforms) — see [Generation Strategies](/guide/variant-pipeline/generation-strategies) for
the trade-offs of each.

## 4. Retrying failures

A failed generation writes a fail marker (throttling retries for
`variant_store.fail_marker_ttl` seconds, default 300) and rethrows — Messenger's own retry
policy applies on top of that. Configure it like any other transport:

```yaml
framework:
    messenger:
        transports:
            async_images:
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
                retry_strategy:
                    max_retries: 3
                    delay: 5000
```

If you'd rather pre-generate known-important variants than wait for a worker to pick them
up, see [CLI Commands](/guide/variant-pipeline/cli-commands) —
`progressive-image:variant:warm` produces exactly the same on-disk artifacts a worker would,
just synchronously and on demand.
