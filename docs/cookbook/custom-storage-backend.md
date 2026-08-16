# Custom Storage Backend

Almost always, you don't need this — pointing `variant_store.storage` at a different
`League\Flysystem\FilesystemOperator` (local disk, S3, ...) already covers "store variants
somewhere else"; see [Variant Pipeline → Storage](/guide/variant-pipeline/storage). Only
reach for a genuinely custom `VariantStorage` implementation if you need something
Flysystem can't model.

## The interface

```php
namespace Tito10047\ProgressiveImageBundle\Variant\Domain\Port;

interface VariantStorage
{
    public function exists(VariantPath $path): bool;
    public function write(VariantPath $path, GeneratedImage $image): void;
    public function read(VariantPath $path): GeneratedImage;
    public function delete(VariantPath $path): void;
    public function publicPath(VariantPath $path): string;
    public function writeFailMarker(VariantPath $path, \DateTimeImmutable $at): void;
    public function failMarkerTimestamp(VariantPath $path): ?\DateTimeImmutable;
}
```

`exists()` is deliberately impure: `ResolveVariantUrlHandler` re-checks it after a
synchronous dispatch within the same request and expects to see the just-written file.

## A minimal example

```php
namespace App\Image;

use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\GeneratedImage;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\OutputFormat;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\VariantPath;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Port\VariantStorage;

final class InMemoryVariantStorage implements VariantStorage
{
    /** @var array<string, GeneratedImage> */
    private array $files = [];
    /** @var array<string, \DateTimeImmutable> */
    private array $failMarkers = [];

    public function exists(VariantPath $path): bool
    {
        return isset($this->files[$path->value]);
    }

    public function write(VariantPath $path, GeneratedImage $image): void
    {
        $this->files[$path->value] = $image;
    }

    public function read(VariantPath $path): GeneratedImage
    {
        return $this->files[$path->value] ?? throw new \RuntimeException('Not found.');
    }

    public function delete(VariantPath $path): void
    {
        unset($this->files[$path->value]);
    }

    public function publicPath(VariantPath $path): string
    {
        return '/media/pgi/'.$path->value;
    }

    public function writeFailMarker(VariantPath $path, \DateTimeImmutable $at): void
    {
        $this->failMarkers[$path->value] = $at;
    }

    public function failMarkerTimestamp(VariantPath $path): ?\DateTimeImmutable
    {
        return $this->failMarkers[$path->value] ?? null;
    }
}
```

## Wiring it in

The Variant context's services are only registered once `variant_store.storage` is set, so
you can't alias `VariantStorage::class` to your own service via that same config key — set
the alias directly in your app's own service configuration, and give
`variant_store.storage` any Flysystem service id (it just needs to exist; nothing else in
your custom implementation has to use it):

```php
// config/services.php
use Tito10047\ProgressiveImageBundle\Variant\Domain\Port\VariantStorage;
use App\Image\InMemoryVariantStorage;

return function (ContainerConfigurator $container) {
    $services = $container->services();
    $services->set(InMemoryVariantStorage::class);
    $services->alias(VariantStorage::class, InMemoryVariantStorage::class)->public();
};
```

## Verifying it behaves correctly

The bundle's own test suite verifies every `VariantStorage` implementation — including the
built-in Flysystem one — against one shared abstract test case,
`Tito10047\ProgressiveImageBundle\Tests\Variant\Contract\VariantStorageContractTest`. Extend
it for your own implementation the same way the bundle does for its in-memory test double:

```php
final class InMemoryVariantStorageContractTest extends VariantStorageContractTest
{
    protected function createStorage(): VariantStorage
    {
        return new InMemoryVariantStorage();
    }
}
```

This buys you assertions like "writing then reading round-trips the exact bytes and
format", "deleting an unwritten path doesn't throw", and "a fail marker's timestamp
survives a write/read cycle" for free — a fake that passes your own ad-hoc tests but
doesn't actually satisfy this contract would be a lie the rest of the pipeline silently
relies on.
