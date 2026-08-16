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

namespace Tito10047\ProgressiveImageBundle\Variant\Domain\Model;

use Tito10047\ProgressiveImageBundle\Variant\Domain\Exception\InvalidFilterDefinition;

final readonly class PointOfInterest
{
    public function __construct(public int $x, public int $y)
    {
        if ($x < 0 || $y < 0) {
            throw new InvalidFilterDefinition(sprintf('PointOfInterest coordinates must be non-negative, got %dx%d.', $x, $y));
        }
    }
}
