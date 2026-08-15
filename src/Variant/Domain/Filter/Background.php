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

final readonly class Background implements Filter
{
    public string $color;

    public function __construct(string $color)
    {
        if (!preg_match('/^#([0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $color)) {
            throw new InvalidFilterDefinition(sprintf('Background color must be a #rrggbb or #rrggbbaa hex value, got "%s".', $color));
        }

        $this->color = strtolower($color);
    }

    public function canonical(): array
    {
        return ['background' => ['color' => $this->color]];
    }
}
