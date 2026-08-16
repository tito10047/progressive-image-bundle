# CLI Commands

Two console commands manage stored variants directly, outside of any HTTP request — both
are only registered when `variant_store.storage` is configured.

## `progressive-image:variant:warm`

Pre-generates variants for a source ahead of the first real request: a deploy hook, a bulk
import script, or just avoiding a cold first hit for a page you know will get traffic
immediately (a campaign landing page, say).

```bash
# warm every configured filter_sets entry for this source
bin/console progressive-image:variant:warm uploads/hero.jpg

# warm only specific filter sets (repeatable)
bin/console progressive-image:variant:warm uploads/hero.jpg -f thumb_small -f thumb_large
```

| Argument/option | Meaning |
|---|---|
| `source` | The logical source path, same as you'd pass as `src` to the Twig component. |
| `-f`, `--filter-set` | A `filter_sets` name to warm. Repeatable. Defaults to every configured filter set when omitted. |

Always generates synchronously and reports per filter set (generated / already existed /
failed), regardless of `generation.strategy` — a warm command that just queues work and
exits immediately hasn't actually warmed anything yet. Exits non-zero if any filter set
failed to generate.

## `progressive-image:variant:remove`

Deletes every stored variant for a source — useful after replacing or deleting the original
file, since content-addressed generation means a changed source naturally gets a new
`VariantId` on its own, but the *old* variants (for the previous bytes) are never cleaned up
automatically.

```bash
bin/console progressive-image:variant:remove uploads/hero.jpg

# see what would be deleted without deleting anything
bin/console progressive-image:variant:remove uploads/hero.jpg --dry-run
```

This command is scoped to a single source — there is no global "purge everything" mode.
`VariantStorage::list()` only ever answers "what exists for this one source", by design (a
"list everything" capability isn't needed by anything else in the pipeline and would add a
second, unverified code path to every storage backend); if you need to clear an entire
storage bucket, that's a job for your storage backend's own tooling (e.g. emptying an S3
bucket/prefix directly), not this command.
