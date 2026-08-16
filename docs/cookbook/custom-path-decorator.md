# Custom Path Decorator

A `PathDecoratorInterface` rewrites the `src` you pass to `<twig:pgi:Image>` before
metadata lookup — e.g. prefixing a CDN host, mapping a logical path to a different storage
key, or reading dimensions from an already-known source instead of re-analyzing the file.

## The interface

```php
namespace Tito10047\ProgressiveImageBundle\Decorators;

interface PathDecoratorInterface
{
    /**
     * @param array<string, mixed> $context
     */
    public function decorate(string $path, array $context = []): string;

    /**
     * @param array<string, mixed> $context
     * @return array{width: int, height: int}|null
     */
    public function getSize(string $path, array $context = []): ?array;
}
```

`Image::postMount()` applies every configured decorator in order to build the "decorated"
`src` actually rendered in the `<img>` tag, and — only when `sizes` wasn't set (no
responsive breakpoints resolved) — asks each decorator for `getSize()` as a fallback source
of width/height if the metadata analyzer didn't already provide one.

## Example: a CDN prefix decorator

```php
namespace App\Image;

use Tito10047\ProgressiveImageBundle\Decorators\PathDecoratorInterface;

final class CdnPathDecorator implements PathDecoratorInterface
{
    public function __construct(private string $cdnHost)
    {
    }

    public function decorate(string $path, array $context = []): string
    {
        return str_starts_with($path, 'http') ? $path : rtrim($this->cdnHost, '/').'/'.ltrim($path, '/');
    }

    public function getSize(string $path, array $context = []): ?array
    {
        return null; // defer to the bundle's own metadata analyzer
    }
}
```

## Wiring it in

Unlike modifiers, decorators are **not** autoconfigured — list them explicitly, in the
order they should run, under `path_decorators`:

```php
// config/services.php
$container->services()
    ->set(App\Image\CdnPathDecorator::class)
    ->arg('$cdnHost', 'https://cdn.example.com');
```

```yaml
# config/packages/progressive_image.yaml
progressive_image:
    path_decorators:
        - App\Image\CdnPathDecorator
```
