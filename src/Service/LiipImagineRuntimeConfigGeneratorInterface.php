<?php

declare(strict_types=1);

namespace Tito10047\ProgressiveImageBundle\Service;

interface LiipImagineRuntimeConfigGeneratorInterface
{
    /**
     * @return array{filterName: string, config: array<string, mixed>}
     */
    public function generate(int $width, int $height, ?string $filter = null, ?string $pointInterest = null, ?int $origWidth = null, ?int $origHeight = null): array;
}
