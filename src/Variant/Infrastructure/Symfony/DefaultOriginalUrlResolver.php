<?php

/*
 * This file is part of the Progressive Image Bundle.
 *
 * (c) Jozef Môstka <https://github.com/tito10047/progressive-image-bundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Symfony;

use Tito10047\ProgressiveImageBundle\Variant\Application\Port\OriginalUrlResolver;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\SourcePath;

/**
 * Same convention as the pre-existing DefaultResponsiveImageUrlGenerator: SourcePath values
 * are already web-servable paths relative to the public root, so the public URL is just the
 * path itself.
 */
final readonly class DefaultOriginalUrlResolver implements OriginalUrlResolver
{
    public function resolve(SourcePath $source): string
    {
        return '/'.implode('/', array_map(rawurlencode(...), explode('/', $source->value)));
    }
}
