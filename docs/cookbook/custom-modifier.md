# Custom Modifier

Modifiers let a `sizes` breakpoint token trigger arbitrary config changes via
`|<modifier>` pipe segments — e.g. `lg:4@square|circle` — without hand-writing a whole
`filter_sets` entry for every combination you might want in a template.

## `ModifierInterface`

```php
namespace Tito10047\ProgressiveImageBundle\Modifier;

interface ModifierInterface
{
    public function supports(string $modifier): bool;

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function modify(string $modifier, array $context): array;
}
```

`ModifierProvider::applyModifiers()` runs every `|`-separated modifier string in a
breakpoint token through every registered `ModifierInterface` whose `supports()` returns
true, threading the (mutated) `context` array through each in turn. That resulting
`context` is what eventually reaches `VariantSpecFactory::create()` as its per-call layer —
see [Filters, Formats & Quality](/guide/variant-pipeline/filters-formats-and-quality#how-a-variantspec-is-built).

The bundle registers one built-in modifier at low priority (`-100`),
`BaseFilterModifier`, which `supports()` *everything* and, if `context['filter']` isn't
already set, sets it to the modifier string itself — this is what makes `|circle` in
`lg:4@square|circle` shorthand for `context: { filter: circle }`, i.e. "apply the
`filter_sets.circle` entry". Because it's low priority, any modifier you register runs
*before* it and can set a different `context['filter']` (or anything else) that
`BaseFilterModifier` will then leave alone.

## Example: a modifier that toggles a watermark

```php
namespace App\Image;

use Tito10047\ProgressiveImageBundle\Modifier\ModifierInterface;

final class WatermarkModifier implements ModifierInterface
{
    public function supports(string $modifier): bool
    {
        return 'watermarked' === $modifier;
    }

    public function modify(string $modifier, array $context): array
    {
        $context['filters']['watermark'] = [
            'image' => 'images/watermark.png',
            'position' => 'bottom_right',
        ];

        return $context;
    }
}
```

```twig
<twig:pgi:Image src="{{ asset('images/hero.jpg') }}" sizes="lg:6|watermarked" alt="Hero" />
```

## Wiring it in

`ModifierInterface` is **autoconfigured** — implementing it is enough, no explicit tagging
or config entry needed:

```php
// config/services.php
$container->services()->set(App\Image\WatermarkModifier::class);
```

`ProgressiveImageExtension::load()` calls
`$container->registerForAutoconfiguration(ModifierInterface::class)->addTag('progressive_image.modifier')`,
and `ModifierProvider` collects every tagged service via a `TaggedIteratorArgument`.
Priority matters if more than one modifier could `supports()` the same string — use
`#[AsTaggedItem]` or an explicit `addTag('progressive_image.modifier', ['priority' => N])`
to control ordering relative to the built-in `BaseFilterModifier` (priority `-100`).

## `FilterModifierInterface`

A second, narrower interface exists for modifying a single already-resolved filter's
options rather than the whole context array:

```php
namespace Tito10047\ProgressiveImageBundle\Modifier;

interface FilterModifierInterface
{
    public function supports(string $filterName): bool;

    /**
     * @param array<string, mixed> $currentOptions
     * @return array<string, mixed>
     */
    public function modify(string $filterName, array $currentOptions): array;
}
```

Also autoconfigured, tagged `pgi.filter_modifier`. **As of this version, nothing in the
bundle collects that tag into a consumer yet** — implementing it registers the service but
has no observable effect. Prefer `ModifierInterface` above until this seam is wired up to
something.
