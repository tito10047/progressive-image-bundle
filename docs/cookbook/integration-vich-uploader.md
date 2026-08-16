# Integrating with VichUploaderBundle

[VichUploaderBundle](https://github.com/dustin10/VichUploaderBundle) handles the upload
side (mapping an entity property to a file on disk); Progressive Image Bundle handles the
display side (placeholders, responsive `srcset`, generated variants). They don't need any
glue code between them — Vich just needs to write into a directory PGI can already resolve.

## The key idea

Vich stores uploads under a `%kernel.project_dir%/public/...`-style path via its own
`uploaders` mapping. As long as one of PGI's `resolvers` points `roots` at that same
directory, the property Vich populates on your entity (the relative filename) is already a
valid PGI `src`/source path — no adapter, no custom loader.

```yaml
# config/packages/vich_uploader.yaml
vich_uploader:
    mappings:
        product_image:
            uri_prefix: /media/products
            upload_destination: '%kernel.project_dir%/public/media/products'
```

```yaml
# config/packages/progressive_image.yaml
progressive_image:
    resolvers:
        default:
            type: filesystem
            roots: ['%kernel.project_dir%/public/media/products', '%kernel.project_dir%/public']
```

## In a template

Vich's `VichImageType`/`vich_uploader` Twig helpers give you the entity's stored filename;
pass that straight to PGI instead of Vich's own `vich_uploader_asset()`:

```twig
{# entity.imageName holds whatever Vich wrote on upload #}
<twig:pgi:Image src="{{ entity.imageName }}" sizes="sm:12 md:6" alt="{{ entity.name }}" />
```

or, for a plain URL (an admin list thumbnail, an API response):

```twig
{{ pgi_filter(entity.imageName, 'admin_thumbnail') }}
```

## In PHP (e.g. an API resource, a Sonata Admin list)

`ResolveFilterUrlHandler` (the service behind `pgi_filter()`) is a public service — inject
it directly wherever you're not in a Twig context:

```php
use Tito10047\ProgressiveImageBundle\Variant\Application\Handler\ResolveFilterUrlHandler;
use Tito10047\ProgressiveImageBundle\Variant\Application\Query\ResolveFilterUrl;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\SourcePath;

final class ProductThumbnailUrlProvider
{
    public function __construct(private readonly ResolveFilterUrlHandler $resolveFilterUrl) {}

    public function forProduct(Product $product): string
    {
        return ($this->resolveFilterUrl)(
            new ResolveFilterUrl(new SourcePath($product->getImageName()), 'admin_thumbnail')
        )->url;
    }
}
```

That's the whole integration — Vich owns the upload lifecycle (naming, deletion,
namer strategies), PGI owns everything downstream of "here's a path."
