# Integrating with EasyAdminBundle

EasyAdminBundle's built-in `ImageField` renders a plain `<img>` from a `basePath()` +
property value — no placeholders, no responsive sizing, no generated variants. Swapping it
for `pgi_filter()` gets you a real thumbnail pipeline (correctly sized, cached, optionally
WebP/AVIF-negotiated) in an admin list/detail view with a small custom field template.

## The key idea

`ImageField::setTemplatePath()` lets you override how the field renders — point it at a
tiny template that calls `pgi_filter()` on the field's raw value instead of concatenating
`basePath()` + value yourself.

```php
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;

ImageField::new('imageName', 'Photo')
    ->setTemplatePath('admin/field/pgi_thumbnail.html.twig')
```

```twig
{# templates/admin/field/pgi_thumbnail.html.twig #}
{% if field.value %}
    <img src="{{ pgi_filter(field.value, 'admin_thumbnail') }}" alt="" class="img-fluid">
{% endif %}
```

For the detail/edit page, where you likely want the real placeholder/zero-CLS behavior
instead of just a thumbnail `<img>`, use the component directly in a custom
`FormField`/template instead:

```twig
<twig:pgi:Image src="{{ field.value }}" sizes="sm:12" alt="" />
```

## A dedicated admin filter set

Keep the admin thumbnail's size/format independent of anything used on the public site:

```yaml
progressive_image:
    filter_sets:
        admin_thumbnail:
            filters:
                thumbnail: { size: [64, 64], mode: outbound }
            format: webp
            quality: 70
```

## Why not just `ImageField::setBasePath()`?

`setBasePath()` only lets EasyAdmin build a URL to the *original* file — there's no hook
for resizing, format negotiation, or placeholders without a custom template regardless.
Routing that same custom template through `pgi_filter()` (shown above) costs one extra line
over what a from-scratch custom field template would need anyway, and gets you a real
generated, correctly-sized thumbnail instead of the browser downscaling a full-resolution
original in every admin list row.
