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

/**
 * Scales the image relative to whatever its dimensions are at this point in the filter
 * chain (not the original source) — e.g. 50.0 halves the current width/height. This is
 * deliberately not Intervention's resizeCanvasRelative() (which pads/crops the canvas
 * around the existing content); it's a proportional resize, matching Liip's own
 * relative_resize semantics.
 */
final readonly class RelativeResize implements Filter
{
    public function __construct(
        public ?float $widthPercent = null,
        public ?float $heightPercent = null,
    ) {
        if (null === $widthPercent && null === $heightPercent) {
            throw new InvalidFilterDefinition('Filter "relative_resize" requires at least one of "width_percent"/"height_percent".');
        }

        if (null !== $widthPercent && $widthPercent <= 0) {
            throw new InvalidFilterDefinition(sprintf('Filter "relative_resize" option "width_percent" must be positive, got %s.', $widthPercent));
        }

        if (null !== $heightPercent && $heightPercent <= 0) {
            throw new InvalidFilterDefinition(sprintf('Filter "relative_resize" option "height_percent" must be positive, got %s.', $heightPercent));
        }
    }

    public function canonical(): array
    {
        return ['relative_resize' => [
            'width_percent' => $this->widthPercent,
            'height_percent' => $this->heightPercent,
        ]];
    }
}
