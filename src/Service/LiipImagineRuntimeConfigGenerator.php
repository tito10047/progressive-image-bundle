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
            $config['filters']['crop'] = [
                'start' => $this->calculateCropStart((int) $poiX, (int) $poiY, $width, $height, $origWidth, $origHeight),
                'size' => [$width, $height],
            ];
        }

        $config['filters']['thumbnail'] = [
            'size' => [$width, $height],
            'mode' => 'outbound',
        ];

        return [
            'filterName' => $filterName,
            'config' => $config,
        ];
    }

    /**
     * @return array{int, int}
     */
    private function calculateCropStart(int $poiX, int $poiY, int $targetWidth, int $targetHeight, int $origWidth, int $origHeight): array
    {
        // POI is expressed in pixel coordinates of the original image.
        $startX = $poiX - (int) ($targetWidth / 2);
        $startY = $poiY - (int) ($targetHeight / 2);

        // Clamp so the crop window stays within the original image.
        $startX = max(0, min($startX, $origWidth - $targetWidth));
        $startY = max(0, min($startY, $origHeight - $targetHeight));

        return [$startX, $startY];
    }
}
