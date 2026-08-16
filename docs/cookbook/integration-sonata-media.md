# Integrating with Sonata Media Bundle

[SonataMediaBundle](https://docs.sonata-project.org/projects/SonataMediaBundle/) has its
own provider/context/format system for generating thumbnails. If you're adopting
Progressive Image Bundle specifically to get Blurhash placeholders, zero-CLS, and
content-addressed variants — things Sonata's own thumbnail provider doesn't do — the
straightforward approach is: let Sonata keep owning upload/storage/media metadata, and let
PGI take over *rendering* in place of Sonata's own `sonata_media` Twig helpers.

## The key idea

A Sonata `MediaInterface` exposes `getProviderReference()` — the filename Sonata itself
stored on its configured filesystem. Point one of PGI's `resolvers` at that same
filesystem/directory, and that reference is a valid PGI source path.

```yaml
# config/packages/progressive_image.yaml
progressive_image:
    resolvers:
        default:
            type: filesystem
            roots: ['%kernel.project_dir%/public/uploads/media'] # wherever your Sonata context stores files
```

## Replacing `sonata_media_thumbnail` with PGI

Instead of:

```twig
{{ sonata_media_thumbnail(media, 'admin') }}
```

use the component directly against the media's reference:

```twig
<twig:pgi:Image src="{{ media.providerReference }}" sizes="sm:12 md:6" alt="{{ media.name }}" />
```

or `pgi_filter()` for a plain URL (an admin list column, an API resource):

```twig
{{ pgi_filter(media.providerReference, 'sonata_thumbnail') }}
```

## Doing it in PHP (an admin list/API context)

```php
use Sonata\MediaBundle\Model\MediaInterface;
use Tito10047\ProgressiveImageBundle\Variant\Application\Handler\ResolveFilterUrlHandler;
use Tito10047\ProgressiveImageBundle\Variant\Application\Query\ResolveFilterUrl;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\SourcePath;

final class SonataMediaThumbnailUrlProvider
{
    public function __construct(private readonly ResolveFilterUrlHandler $resolveFilterUrl) {}

    public function forMedia(MediaInterface $media, string $filterSet = 'sonata_thumbnail'): string
    {
        return ($this->resolveFilterUrl)(
            new ResolveFilterUrl(new SourcePath($media->getProviderReference()), $filterSet)
        )->url;
    }
}
```

## A `filter_sets` entry per Sonata "format"

Sonata's `context`/`formats` config (width/height/quality per named format) maps directly
onto PGI's `filter_sets`:

```yaml
progressive_image:
    filter_sets:
        sonata_thumbnail:
            filters:
                thumbnail: { size: [100, 100], mode: outbound }
        sonata_big:
            filters:
                thumbnail: { size: [500, 500], mode: inset }
```

This is a rendering-layer swap, not a migration — Sonata's admin UI, upload workflow, and
media metadata all keep working exactly as before; only the `sonata_media_*` Twig helpers
get replaced with PGI's.
