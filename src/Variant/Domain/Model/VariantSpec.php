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
        public bool $progressive = false,
        public bool $stripMetadata = false,
    ) {
    }

    /**
     * @return array{filters: list<array<string, mixed>>, format: string, quality: int, progressive: bool, strip_metadata: bool}
     */
    public function canonical(): array
    {
        return [
            'filters' => $this->filters->canonical(),
            'format' => $this->format->value,
            // Normalized to a constant for formats whose encoder ignores quality (PNG), so
            // two specs that only differ in a setting with no effect on the actual output
            // don't hash to different VariantIds and get generated/stored twice.
            'quality' => $this->format->usesQuality() ? $this->quality->value : 0,
            'progressive' => $this->progressive,
            'strip_metadata' => $this->stripMetadata,
        ];
    }
}
