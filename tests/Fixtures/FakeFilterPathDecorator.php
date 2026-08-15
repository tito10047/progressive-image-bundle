<?php

/*
 * This file is part of the Progressive Image Bundle.
 *
 * (c) Jozef Môstka <https://github.com/tito10047/progressive-image-bundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tito10047\ProgressiveImageBundle\Tests\Fixtures;

use Tito10047\ProgressiveImageBundle\Decorators\PathDecoratorInterface;

/**
 * Deterministic stand-in for the bundle's old decorator implementation, for tests that
 * exercise the generic PathDecoratorInterface extension point (path_decorators config)
 * without needing a real backend: mirrors its exact contract (raw context['filter'], no
 * computed suffix).
 */
final class FakeFilterPathDecorator implements PathDecoratorInterface
{
    public function decorate(string $path, array $context = []): string
    {
        $filter = $context['filter'] ?? null;
        if (!$filter) {
            return $path;
        }

        return 'http://localhost/media/cache/resolve/'.$filter.$path;
    }

    public function getSize(string $path, array $context = []): ?array
    {
        return null;
    }
}
