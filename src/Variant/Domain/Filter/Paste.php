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

use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\SourcePath;

/**
 * Pastes another Source onto the current image at an absolute (x, y) offset from the
 * top-left corner — a badge/logo overlay at a fixed spot, as opposed to Watermark's
 * alignment-relative positioning.
 */
final readonly class Paste implements Filter
{
    public function __construct(
        public SourcePath $image,
        public int $x = 0,
        public int $y = 0,
    ) {
    }

    public function canonical(): array
    {
        return ['paste' => [
            'image' => $this->image->value,
            'x' => $this->x,
            'y' => $this->y,
        ]];
    }
}
