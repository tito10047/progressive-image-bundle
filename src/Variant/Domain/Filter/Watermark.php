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

use Tito10047\ProgressiveImageBundle\Variant\Domain\Exception\InvalidFilterDefinition;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\SourcePath;

final readonly class Watermark implements Filter
{
    public function __construct(
        public SourcePath $image,
        public WatermarkPosition $position,
        public int $opacity = 100,
    ) {
        if ($opacity < 0 || $opacity > 100) {
            throw new InvalidFilterDefinition(sprintf('Watermark opacity must be 0-100, got %d.', $opacity));
        }
    }

    public function canonical(): array
    {
        return ['watermark' => [
            'image' => $this->image->value,
            'position' => $this->position->value,
            'opacity' => $this->opacity,
        ]];
    }
}
