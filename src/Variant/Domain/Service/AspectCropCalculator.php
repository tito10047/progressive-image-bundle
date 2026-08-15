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

namespace Tito10047\ProgressiveImageBundle\Variant\Domain\Service;

use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\CropBox;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\Dimensions;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\PointOfInterest;

/**
 * Finds the largest region in the original image that matches the target aspect ratio,
 * centres it on the POI pixel, and returns the resulting CropBox. The caller is expected
 * to follow this crop with a Thumbnail::inset() to scale down to the exact target size.
 *
 * Ported verbatim (including integer rounding behaviour) from the pre-DDD
 * LiipImagineRuntimeConfigGenerator::calculateAspectCrop() to preserve pixel parity —
 * do not "clean up" the rounding without re-running the AspectCropCalculatorTest golden
 * dataset against the old Liip pipeline output.
 */
final readonly class AspectCropCalculator
{
    public function calculate(PointOfInterest $poi, Dimensions $target, Dimensions $original): CropBox
    {
        if ($original->width / $original->height > $target->width / $target->height) {
            $cropHeight = $original->height;
            $cropWidth = (int) round($original->height * $target->width / $target->height);
        } else {
            $cropWidth = $original->width;
            $cropHeight = (int) round($original->width * $target->height / $target->width);
        }

        $startX = $poi->x - (int) ($cropWidth / 2);
        $startY = $poi->y - (int) ($cropHeight / 2);

        $startX = max(0, min($startX, $original->width - $cropWidth));
        $startY = max(0, min($startY, $original->height - $cropHeight));

        return CropBox::within($startX, $startY, new Dimensions($cropWidth, $cropHeight), $original);
    }
}
