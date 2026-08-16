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

namespace Tito10047\ProgressiveImageBundle\Variant\Application\Query;

use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\SourcePath;

/**
 * Unlike ResolveVariantUrl, carries no width/height — the named filter set's own filters
 * (or lack of any sizing filter) are used exactly as configured. Used for generating a
 * variant URL outside the responsive/breakpoint component (pgi_filter(), the on-the-fly
 * resolve route).
 */
final readonly class ResolveFilterUrl
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        public SourcePath $source,
        public string $filterSet,
        public array $context = [],
    ) {
    }
}
