<?php

/*
 * This file is part of the Progressive Image Bundle.
 *
 * (c) Jozef Môstka <https://github.com/tito10047/progressive-image-bundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tito10047\ProgressiveImageBundle\UrlGenerator;

interface ResponsiveImageUrlGeneratorInterface
{
    public function generateUrl(string $path, int $targetW, ?int $targetH, ?string $pointInterest = null): string;
}
