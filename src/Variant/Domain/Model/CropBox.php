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

final readonly class CropBox
{
    public function __construct(public int $startX, public int $startY, public Dimensions $size)
    {
        if ($startX < 0 || $startY < 0) {
            throw new InvalidFilterDefinition(sprintf('CropBox start must be non-negative, got %dx%d.', $startX, $startY));
        }
    }

    public static function within(int $startX, int $startY, Dimensions $size, Dimensions $bounds): self
    {
        if ($startX + $size->width > $bounds->width || $startY + $size->height > $bounds->height) {
            throw new InvalidFilterDefinition(sprintf(
                'CropBox [%d,%d %dx%d] does not fit within bounds %dx%d.',
                $startX,
                $startY,
                $size->width,
                $size->height,
                $bounds->width,
                $bounds->height
            ));
        }

        return new self($startX, $startY, $size);
    }
}
