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

use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\FilterChain;

final readonly class VariantSpec
{
    public function __construct(
        public FilterChain $filters,
        public OutputFormat $format,
        public Quality $quality,
    ) {
    }

    /**
     * @return array{filters: list<array<string, mixed>>, format: string, quality: int}
     */
    public function canonical(): array
    {
        return [
            'filters' => $this->filters->canonical(),
            'format' => $this->format->value,
            'quality' => $this->quality->value,
        ];
    }
}
