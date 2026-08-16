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

final readonly class Dimensions
{
    public function __construct(public int $width, public int $height)
    {
        if ($width < 1 || $height < 1) {
            throw new InvalidFilterDefinition(sprintf('Dimensions must be positive, got %dx%d.', $width, $height));
        }
    }

    public function aspectRatio(): float
    {
        return $this->width / $this->height;
    }

    public function isWiderThan(self $other): bool
    {
        return $this->aspectRatio() > $other->aspectRatio();
    }
}
