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

namespace Tito10047\ProgressiveImageBundle\Variant\Domain\Filter;

use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\CropBox;

final readonly class Crop implements Filter
{
    public function __construct(public CropBox $box)
    {
    }

    public function canonical(): array
    {
        return ['crop' => [
            'x' => $this->box->startX,
            'y' => $this->box->startY,
            'w' => $this->box->size->width,
            'h' => $this->box->size->height,
        ]];
    }
}
