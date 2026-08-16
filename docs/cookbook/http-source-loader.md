# Remote (HTTP) Source Loading

By default the bundle only reads source images from local disk (via `resolvers` /
`FileSystemResolver` / `AssetMapperResolver`). If your originals live behind a URL instead —
another service's CDN, a headless CMS, a third-party asset host — `variant_source.http` lets
`SourcePath` values be absolute `http://`/`https://` URLs instead of local paths.

This is off by default, and stays off unless you explicitly allowlist hosts: fetching
whatever URL a caller (or a filter set built from user input) supplies is a textbook SSRF
surface, so the bundle fails closed rather than open.

## Enabling it

```yaml
# config/packages/progressive_image.yaml
progressive_image:
    variant_source:
        http:
            enabled: true
            allowed_hosts: ['images.example.com', 'cdn.partner.example']
            timeout: 5 # seconds, defaults to 5
```

`allowed_hosts` is required (and validated at compile time) whenever `enabled: true` — an
empty allowlist with HTTP loading enabled is rejected outright rather than silently allowing
every host.

Once enabled, any `SourcePath` starting with `http://` or `https://` is routed to the remote
reader; everything else keeps going through the existing local resolver chain, unchanged:

```twig
<twig:pgi:Image src="https://images.example.com/hero.jpg" sizes="..." />
```

```twig
{{ pgi_filter('https://images.example.com/hero.jpg', 'thumb_small') }}
```

## How it works

A new `HttpSourceReader` (implementing the same `SourceReader` port as the built-in local
reader) fetches the URL via `symfony/http-client`, checks the response status and decodes
the bytes with `getimagesizefromstring()` — exactly the same `SourceImage` shape
(stream + dimensions + mime) the rest of the pipeline already expects from a local file.
`ChainSourceReader` picks between it and the local reader based on the `SourcePath` value.

Requires `symfony/http-client` (`composer require symfony/http-client` if it isn't already
pulled in transitively) — enabling `variant_source.http` without it installed fails fast at
container-compile time with a clear message, the same pattern the bundle uses for the
`messenger`-dependent async generation strategy.

## Verifying it behaves correctly

Like every other port in this bundle, `SourceReader` has a shared behavioral contract test —
`Tito10047\ProgressiveImageBundle\Tests\Variant\Contract\SourceReaderContractTestCase` — run
against both the built-in local reader and `HttpSourceReader`. If you write your own
`SourceReader` (e.g. a signed-S3-URL variant, or one with retry/backoff), extend it the same
way:

```php
final class MySourceReaderContractTest extends SourceReaderContractTestCase
{
    protected function createReader(): SourceReader { /* ... */ }
    protected function existingSourcePath(): SourcePath { /* ... */ }
    protected function expectedDimensions(): Dimensions { /* ... */ }
    protected function expectedMime(): string { /* ... */ }
    protected function missingSourcePath(): SourcePath { /* ... */ }
}
```

A reader that passes only your own ad-hoc tests but not this shared contract is a lie
`GenerateVariantHandler` and `InterventionImageManipulator` would silently rely on.
