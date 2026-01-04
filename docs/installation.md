# Installation

Progressive Image Bundle requires **PHP 8.2+** and **Symfony 6.4+**.

## 1. Installation via Composer

Run the following command in your terminal:

```console
composer require tito10047/progressive-image-bundle
```

## 2. Registering the Bundle

If you are not using Symfony Flex, you must register the bundle manually in `config/bundles.php`:

```php
// config/bundles.php
return [
    // ...
    Tito10047\ProgressiveImageBundle\ProgressiveImageBundle::class => ['all' => true],
];
```

## 3. Importing Routing

To use dynamic image filtering (e.g., via LiipImagine), add the routing to `config/routes.yaml`:

```yaml
# config/routes.yaml
progressive_image:
    resource: "@ProgressiveImageBundle/config/routes.php"
```

## 4. Frontend Dependencies

The bundle uses Symfony UX Twig Components and Stimulus. If you are using Symfony UX, these components should activate automatically. For proper functioning of
placeholders and loading effects, ensure you have a Stimulus environment installed and configured.

You can also use pre-prepared CSS styles for Tailwind or Bootstrap, which can be found in the `assets/styles/` folder.
