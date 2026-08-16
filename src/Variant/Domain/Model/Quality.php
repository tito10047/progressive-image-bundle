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

final readonly class Quality
{
    public function __construct(public int $value)
    {
        if ($value < 1 || $value > 100) {
            throw new InvalidFilterDefinition(sprintf('Quality must be 1-100, got %d.', $value));
        }
    }
}
