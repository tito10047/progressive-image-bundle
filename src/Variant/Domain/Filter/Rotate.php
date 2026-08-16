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

final readonly class Rotate implements Filter
{
    public int $degrees;

    public function __construct(int $degrees)
    {
        $this->degrees = (($degrees % 360) + 360) % 360;
    }

    public function canonical(): array
    {
        return ['rotate' => ['degrees' => $this->degrees]];
    }
}
