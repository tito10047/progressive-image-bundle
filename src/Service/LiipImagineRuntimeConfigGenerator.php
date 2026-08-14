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

use Liip\ImagineBundle\Exception\Imagine\Filter\NonExistingFilterException;
use Liip\ImagineBundle\Imagine\Filter\FilterConfiguration;

final class LiipImagineRuntimeConfigGenerator implements LiipImagineRuntimeConfigGeneratorInterface
{
    /**
     * @param array<string, mixed> $imageConfigs
     */
    public function __construct(
        private readonly FilterConfiguration $filterConfiguration,
        private readonly array $imageConfigs = [],
    ) {
    }

    /**
     * @return array{filterName: string, config: array<string, mixed>}
     */
    public function generate(int $width, int $height, ?string $filter = null, ?string $pointInterest = null, ?int $origWidth = null, ?int $origHeight = null, array $context = []): array
    {
        $filterName = $filter ? sprintf('%s_%dx%d', $filter, $width, $height) : sprintf('%dx%d', $width, $height);

        if ($pointInterest) {
            $filterName .= '_'.$pointInterest;
        }

        if ($context) {
            $filterName .= '_'.substr(md5(serialize($context)), 0, 5);
        }

        if ($this->imageConfigs) {
            $filterName .= '_'.substr(md5(serialize($this->imageConfigs)), 0, 5);
        }

        $config = [];
        if (null !== $filter) {
            try {
                $config = $this->filterConfiguration->get($filter);
            } catch (NonExistingFilterException) {
            }
        }

        $config = array_replace_recursive($config, $this->imageConfigs, $context);

        if (!isset($config['filters'])) {
            $config['filters'] = [];
        }

        if ($pointInterest && $origWidth && $origHeight) {
            [$poiX, $poiY] = explode('x', $pointInterest);
            [$cropStart, $cropSize] = $this->calculateAspectCrop(
                (int) $poiX, (int) $poiY,
                $width, $height,
                $origWidth, $origHeight
            );
            $config['filters']['crop'] = [
                'start' => $cropStart,
                'size' => $cropSize,
            ];
            $config['filters']['thumbnail'] = [
                'size' => [$width, $height],
                'mode' => 'inset',
            ];
        } else {
            $config['filters']['thumbnail'] = [
                'size' => [$width, $height],
                'mode' => 'outbound',
            ];
        }

        return [
            'filterName' => $filterName,
            'config' => $config,
        ];
    }

    /**
     * Finds the largest region in the original image that matches the target aspect ratio,
     * centres it on the POI pixel, and returns [start, size] for a LiipImagine crop filter.
     * The caller should follow this crop with a thumbnail/inset to scale to the target size.
     *
     * @return array{array{int,int}, array{int,int}}
     */
    private function calculateAspectCrop(int $poiX, int $poiY, int $targetWidth, int $targetHeight, int $origWidth, int $origHeight): array
    {
        if ($origWidth / $origHeight > $targetWidth / $targetHeight) {
            // Original is wider than target → constrain by height, crop excess width
            $cropHeight = $origHeight;
            $cropWidth = (int) round($origHeight * $targetWidth / $targetHeight);
        } else {
            // Original is taller than target → constrain by width, crop excess height
            $cropWidth = $origWidth;
            $cropHeight = (int) round($origWidth * $targetHeight / $targetWidth);
        }

        $startX = $poiX - (int) ($cropWidth / 2);
        $startY = $poiY - (int) ($cropHeight / 2);

        $startX = max(0, min($startX, $origWidth - $cropWidth));
        $startY = max(0, min($startY, $origHeight - $cropHeight));

        return [[$startX, $startY], [$cropWidth, $cropHeight]];
    }
}
