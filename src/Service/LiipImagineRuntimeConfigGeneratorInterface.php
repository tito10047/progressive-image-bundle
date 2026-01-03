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

namespace Tito10047\ProgressiveImageBundle\Service;

interface LiipImagineRuntimeConfigGeneratorInterface
{
    /**
     * @return array{filterName: string, config: array<string, mixed>}
     */
    public function generate(int $width, int $height, ?string $filter = null, ?string $pointInterest = null, ?int $origWidth = null, ?int $origHeight = null): array;
}
