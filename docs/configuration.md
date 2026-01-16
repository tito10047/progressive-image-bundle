# <img src="logo/SVG/ImageBundleLogo-01-cropped.svg" width="100" align="center" alt="Logo"> Configuration

The bundle configuration is usually located in `config/packages/progressive_image.yaml`. All settings are optional as the bundle has sensible default values.

## Complete Configuration Example

```yaml
progressive_image:
    # Image processing driver ('gd' or 'imagick')
    driver: "imagick"

    # Image loading service (LoaderInterface implementation)
    loader: null

    # Default resolver for image paths
    resolver: "default"

    # Enable/disable HTML cache for components
    image_cache_enabled: true

    # Cache service (PSR-6 or PSR-16)
    image_cache_service: "cache.app"

    # Cache time-to-live in seconds
    ttl: 86400

    # Retina support settings
    retina:
        enabled: true
        multipliers: [1, 2]

    # Blurhash generation settings
    hash_resolution:
        width: 10
        height: 8

    # Path to fallback image if original is not found TODO:not implemented
    fallback_image: null

    # Resolver definitions
    resolvers:
        public_files:
            type: "filesystem"
            roots: [ '%kernel.project_dir%/public' ]
            allowUnresolvable: true
        assets:
            type: "asset_mapper"
        chain:
            type: "chain"
            resolvers:
                - 'public_files'
                - 'assets'

    # Responsive strategy (Grid and Aspect Ratios)
    responsive_strategy:
        grid:
            framework: tailwind # 'tailwind', 'bootstrap' or 'custom'
            columns: 12
            gutter: 0
            # Definition of custom layouts (if framework is 'custom')
            layouts:
                2xl: { min_viewport: 1536, max_container: 1536 }
                xl: { min_viewport: 1280, max_container: 1280 }
                # ...
        ratios:
            landscape: "16/9"
            portrait: "3/4"
            square: "1/1"

    # List of path decorators (e.g., for LiipImagine)
    path_decorators:
        - "progressive_image.decorator.liip_imagine"

    # Global image configuration (quality, post-processors, etc.)
    # These settings are applied to all generated images.
    image_configs:
        quality: 75
        post_processors:
            cwebp: { q: 75, m: 6 }
```

## Detailed Section Description

### Resolvers (`resolvers`)

They determine how the bundle searches for image files on disk or within the project.

- **filesystem**: Classic folder-based search (e.g., `public`).
- **asset_mapper**: Integration with Symfony AssetMapper.
- **chain**: Allows combining multiple resolvers.

### Responsive Strategy (`responsive_strategy`)

This is where you define how image sizes are calculated. For more information, see the [Responsive Strategy](responsive-strategy.md) section.

#### CSS Frameworks & Custom CSS Generation

The bundle supports `tailwind`, `bootstrap`, and `custom` frameworks.

- **Tailwind & Bootstrap**: These frameworks have pre-defined breakpoints. The bundle includes pre-generated CSS files for these frameworks in `assets/styles/`.
- **Custom**: If you use a `custom` framework, you need to define your own breakpoints in the `layouts` section. To handle the responsive behavior of images (like width
  and aspect ratio via CSS variables), you need a corresponding CSS file.

**Pre-defined CSS files:**

- Tailwind: `assets/styles/progressive-image-tailwind.css`
- Bootstrap: `assets/styles/progressive-image-bootstrap.css`

**Why use the generator for custom frameworks?**
The bundle uses CSS variables (e.g., `--img-width-md`) to communicate the calculated image size from PHP to your CSS. For `custom` frameworks, these variables must be
properly mapped to the `.progressive-image-container` class within media queries. The generator automates this process, creating a CSS file with all necessary media
queries and fallback logic based on your runtime configuration.

To generate this file, use the following command:

```bash
php bin/console progressive-image:generate-custom-css [path]
```

- `[path]` (optional): Path where the file will be generated. Default is `assets/styles`.
- The command creates `progressive-image-custom.css`.

Example of generated CSS logic:

```css
.progressive-image-container {
    width: var(--img-width-md, var(--img-width-sm, var(--img-width)));
}
@media (min-width: 768px) {
    .progressive-image-container {
        width: var(--img-width-md, var(--img-width-sm, var(--img-width)));
    }
}
```

This ensures that if a variable for a specific breakpoint is missing, it falls back to the next smaller available size.

### Cache

The bundle can cache generated HTML components, saving time on subsequent Blurhash generation and metadata reading.

### Retina Support (`retina`)

The bundle supports generating images for high-density (Retina) displays.

- **retina**:
  - **enabled**: Enables or disables retina support globally. Default is `true`.
  - **multipliers**: Defines the multipliers for which additional image versions should be generated. Default is `[1, 2]`. For each multiplier, a corresponding version is
    added to the `srcset` attribute.

### Decorators (`path_decorators`)

They allow modifying the final image URL. Most commonly used for integration with LiipImagine, which generates thumbnails.

### Image Configs (`image_configs`)

Allows defining global parameters for image generation that are passed to the underlying library (e.g. LiipImagine).

- **quality**: Defines the output image quality.
- **post_processors**: Configures post-processing filters like `cwebp` for WebP conversion.
