# Custom Image Manipulator

The built-in `InterventionImageManipulator` wraps
[intervention/image](https://image.intervention.io/) (GD or Imagick, per the `driver`
config). If you need a different image processing engine entirely — a native extension,
a remote image-processing API, ImageMagick invoked directly via CLI — implement the
`ImageManipulator` port instead.

## The interface

```php
namespace Tito10047\ProgressiveImageBundle\Variant\Domain\Port;

interface ImageManipulator
{
    public function process(SourceImage $source, VariantSpec $spec): GeneratedImage;
}
```

Your implementation receives the already-read `SourceImage` (a `stream` resource, its
`dimensions`, and a `mime` hint) and a fully-resolved `VariantSpec` — a `FilterChain` (iterate it; each `Filter` is one of
`Crop`/`Resize`/`Rotate`/`Thumbnail`/`Background`/`Watermark`, see
[Filters, Formats & Quality](/guide/variant-pipeline/filters-formats-and-quality)), an
`OutputFormat`, and a `Quality`. It must return a `GeneratedImage` (encoded bytes + the
format actually produced).

```php
namespace App\Image;

use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\GeneratedImage;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\SourceImage;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\VariantSpec;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Port\ImageManipulator;

final class RemoteApiImageManipulator implements ImageManipulator
{
    public function __construct(private HttpClientInterface $client)
    {
    }

    public function process(SourceImage $source, VariantSpec $spec): GeneratedImage
    {
        $response = $this->client->request('POST', 'https://images.example.com/process', [
            'body' => [
                'image' => stream_get_contents($source->stream),
                'spec' => $spec->canonical(),
            ],
        ]);

        return new GeneratedImage($response->getContent(), $spec->format);
    }
}
```

## Wiring it in

```php
// config/services.php
use Tito10047\ProgressiveImageBundle\Variant\Domain\Port\ImageManipulator;
use App\Image\RemoteApiImageManipulator;

return function (ContainerConfigurator $container) {
    $services = $container->services();
    $services->set(RemoteApiImageManipulator::class)
        ->args([service('http_client')]);
    $services->alias(ImageManipulator::class, RemoteApiImageManipulator::class);
};
```

There's no config-driven hook for this (unlike `driver: gd|imagick`, which only selects
between the two built-in Intervention drivers) — aliasing the port directly is the
supported way to fully replace the image engine. Make sure this service definition is
loaded *after* the bundle's own container extension runs (the default for app-level
`config/services.php`), so your alias wins over the bundle's own
`InterventionImageManipulator` registration.
