# Custom Post-Processor

`PostProcessor`s run after `ImageManipulator::process()` and before the result is stored —
an optional extra pass over the already-encoded bytes. The bundle ships four
(`jpegoptim`, `pngquant`, `cwebp`, `avifenc`, see
[Filters, Formats & Quality](/guide/variant-pipeline/filters-formats-and-quality#post-processors)),
all shelling out to a CLI binary via a shared `CliPostProcessor` base class. Your own
doesn't have to.

## The interface

```php
namespace Tito10047\ProgressiveImageBundle\Variant\Domain\Port;

interface PostProcessor
{
    public function supports(OutputFormat $format): bool;

    public function process(GeneratedImage $image): GeneratedImage;
}
```

`GenerateVariantHandler` runs **every** registered `PostProcessor` whose `supports()`
returns true for the variant's format, in registration order — so more than one can apply
to the same format if that's what you want.

## Example: a pure-PHP post-processor

```php
namespace App\Image;

use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\GeneratedImage;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\OutputFormat;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Port\PostProcessor;

final class ExifStrippingPostProcessor implements PostProcessor
{
    public function supports(OutputFormat $format): bool
    {
        return OutputFormat::Jpeg === $format;
    }

    public function process(GeneratedImage $image): GeneratedImage
    {
        // e.g. strip EXIF via a library of your choice
        return $image->withContents($strippedBytes);
    }
}
```

## Wiring it in

`GenerateVariantHandler`'s `$postProcessors` argument is a
`TaggedIteratorArgument('progressive_image.variant.post_processor')` — tag your service and
it's picked up automatically, alongside any enabled built-in post-processors:

```php
// config/services.php
use App\Image\ExifStrippingPostProcessor;

return function (ContainerConfigurator $container) {
    $container->services()
        ->set(ExifStrippingPostProcessor::class)
        ->tag('progressive_image.variant.post_processor');
};
```

If you're writing a CLI-backed post-processor like the built-in ones, extend
`Tito10047\ProgressiveImageBundle\Variant\Infrastructure\PostProcess\CliPostProcessor` and
implement `buildCommand(string $inputPath, string $outputPath): array` — it handles writing
the input to a temp file, running the process (via `symfony/process`, with a timeout), and
reading the result back for you.
