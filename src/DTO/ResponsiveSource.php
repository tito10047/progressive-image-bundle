<?php

/*
 * This file is part of the Progressive Image Bundle.
 *
 * (c) Jozef Môstka <https://github.com/tito10047/progressive-image-bundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tito10047\ProgressiveImageBundle\DTO;

class ResponsiveSource implements ResponsiveSourceInterface
{
    public function __construct(
        private readonly ?string $media,
        private readonly string $srcset,
        private readonly string $sizes,
        private readonly ?string $type = null,
    ) {
    }

    public function getMedia(): ?string
    {
        return $this->media;
    }

    public function getSrcset(): string
    {
        return $this->srcset;
    }

    public function getSizes(): string
    {
        return $this->sizes;
    }

    public function getType(): ?string
    {
        return $this->type;
    }
}
