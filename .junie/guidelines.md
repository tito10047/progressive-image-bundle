# Progressive Image Bundle - Development Guidelines

## 1. Build and Configuration Instructions

### Dependency Installation

To install all necessary packages, use Composer:

```bash
composer install
```

### Project Configuration

The bundle is designed to be "Zero-config", but for advanced usage, you can modify the configuration in `config/packages/progressive_image.yaml`.

Basic settings include:

- **driver**: `gd` or `imagick` (default: `imagick`)
- **responsive_strategy**: definition of the grid (Tailwind/Bootstrap) and aspect ratios.
- **image_cache_enabled**: enables cache for Twig components.

More information can be found in `docs/configuration.md`.

## 2. Testing

### Supported Versions

The project supports the following versions:

- **PHP**: 8.2 - 8.5
- **Symfony**: 6.4 - 8.0

When requested, it may be necessary to run tests on the lowest and highest supported configurations to ensure compatibility.

### Running Tests

The project uses PHPUnit for unit and functional tests.

Run all tests:

```bash
php84 vendor/bin/phpunit
```

Run a specific test:

```bash
php84 vendor/bin/phpunit tests/Unit/Service/MetadataReaderTest.php
```

### Adding New Tests

- **Unit Tests**: Place in `tests/Unit`. These tests should be isolated and use mocks.
- **Functional Tests**: Place in `tests/Functional`. These tests verify integration with the Symfony kernel. You can use `PGITestCase` to boot the testing kernel.

### Simple Test Example

Create a file in `tests/Unit/ExampleTest.php`:

```php
<?php

namespace Tito10047\ProgressiveImageBundle\Tests\Unit;

use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    public function testExample(): void
    {
        $value = true;
        $this->assertTrue($value);
    }
}
```

## 3. Additional Development Information

### Code Style

The project follows the Symfony code style. To automatically fix formatting, use:

```bash
composer fix-cs
```

Or directly:

```bash
vendor/bin/php-cs-fixer fix
```

### Static Analysis

PHPStan is used to verify type safety and detect errors:

```bash
vendor/bin/phpstan analyse src --level 5
```

### Communication and Language

- **Communication**: Always communicate with me (the user) in **Slovak language**.
- **Code and Documentation**: Code, comments in code, and official documentation (files in `docs/`, `README.md`, etc.) must always be in **English language**.

### Important Commands

- `composer test` - Runs PHPUnit tests.
- `composer fix-cs` - Fixes code style.
- `composer check-cs` - Checks code style without making changes.
