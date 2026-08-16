# Custom Responsive URL Generator

`ResponsiveImageUrlGeneratorInterface` decides what URL each responsive breakpoint's
`srcset` candidate points to. There are three built-in options, resolved in this priority
order:

1. an explicit `responsive_strategy.generator` — always wins;
2. `VariantResponsiveImageUrlGenerator`, automatically active once `variant_store.storage`
   is configured (this is what makes the Variant pipeline actually produce resized files);
3. `DefaultResponsiveImageUrlGenerator` — an identity passthrough, used when neither of the
   above applies.

Implement your own if you already have an external image-resizing service (a SaaS image
CDN, an existing internal resizer) and don't want the Variant pipeline generating files at
all.

## The interface

```php
namespace Tito10047\ProgressiveImageBundle\UrlGenerator;

interface ResponsiveImageUrlGeneratorInterface
{
    public function generateUrl(string $path, int $targetW, ?int $targetH, ?string $pointInterest = null, array $context = []): string;
}
```

Called once per resolved breakpoint/retina-multiplier/negotiated-format combination by
`ResponsiveAttributeGenerator` while building a `<picture>`'s `srcset`.

## Example: an external image CDN

```php
namespace App\Image;

use Tito10047\ProgressiveImageBundle\UrlGenerator\ResponsiveImageUrlGeneratorInterface;

final class ImgixUrlGenerator implements ResponsiveImageUrlGeneratorInterface
{
    public function __construct(private string $imgixHost)
    {
    }

    public function generateUrl(string $path, int $targetW, ?int $targetH, ?string $pointInterest = null, array $context = []): string
    {
        $query = ['w' => $targetW];
        if (null !== $targetH) {
            $query['h'] = $targetH;
            $query['fit'] = 'crop';
        }

        return rtrim($this->imgixHost, '/').'/'.ltrim($path, '/').'?'.http_build_query($query);
    }
}
```

## Wiring it in

```php
// config/services.php
$container->services()
    ->set(App\Image\ImgixUrlGenerator::class)
    ->arg('$imgixHost', 'https://your-source.imgix.net');
```

```yaml
# config/packages/progressive_image.yaml
progressive_image:
    responsive_strategy:
        generator: App\Image\ImgixUrlGenerator
```

Setting this makes the bundle skip the Variant pipeline for URL generation entirely — no
files are generated locally, only URLs. `variant_store.storage` can stay unset in that
case, unless you still want the wait/pending flow for some other purpose.
