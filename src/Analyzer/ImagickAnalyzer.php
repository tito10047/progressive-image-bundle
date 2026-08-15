<?php

/*
 * This file is part of the Progressive Image Bundle.
 *
 * (c) Jozef Môstka <https://github.com/tito10047/progressive-image-bundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tito10047\ProgressiveImageBundle\Analyzer;

use kornrunner\Blurhash\Blurhash;
use Tito10047\ProgressiveImageBundle\DTO\ImageMetadata;
use Tito10047\ProgressiveImageBundle\Exception\ImageProcessingException;
use Tito10047\ProgressiveImageBundle\Loader\LoaderInterface;

final class ImagickAnalyzer implements ImageAnalyzerInterface
{
    public function __construct(
        private readonly int $componentsX = 4,
        private readonly int $componentsY = 3,
    ) {
    }

    public function analyze(LoaderInterface $loader, string $path): ImageMetadata
    {
        $image = new \Imagick();

        try {
            // 3. Loading directly from handle
            $image->readImageFile($loader->load($path));
            $orgWidth = $image->getImageWidth();
            $orgHeight = $image->getImageHeight();
            $image->thumbnailImage(64, 64, true);
            $width = $image->getImageWidth();
            $height = $image->getImageHeight();

            self::assertValidDimensions($width, $height);

            $pixels = $image->exportImagePixels(0, 0, $width, $height, 'RGB', \Imagick::PIXEL_CHAR);

            $formattedPixels = [];
            for ($y = 0; $y < $height; ++$y) {
                $row = [];
                for ($x = 0; $x < $width; ++$x) {
                    $offset = ($y * $width + $x) * 3;
                    $row[] = [
                        $pixels[$offset],
                        $pixels[$offset + 1],
                        $pixels[$offset + 2],
                    ];
                }
                $formattedPixels[] = $row;
            }
        } catch (ImageProcessingException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new ImageProcessingException('Imagick could not load data from stream: '.$e->getMessage());
        } finally {
            $image->clear();
        }

        $hash = Blurhash::encode($formattedPixels, $this->componentsX, $this->componentsY);

        return new ImageMetadata(
            $hash,
            $orgWidth,
            $orgHeight
        );
    }

    private static function assertValidDimensions(int $width, int $height): void
    {
        if ($width < 1 || $height < 1) {
            throw new ImageProcessingException(sprintf(
                'Cannot compute pixel data for a degenerate image (width=%d, height=%d).',
                $width,
                $height
            ));
        }
    }
}
