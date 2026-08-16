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

final class GdImageAnalyzer implements ImageAnalyzerInterface
{
    public function __construct(
        private readonly int $componentsX = 4,
        private readonly int $componentsY = 3,
    ) {
    }

    public function analyze(LoaderInterface $loader, string $path): ImageMetadata
    {
        $stream = $loader->load($path);
        $data = stream_get_contents($stream);

        if (false === $data) {
            throw new ImageProcessingException('Failed to load data from loader for path: '.$path);
        }

        $gdWarning = null;
        set_error_handler(static function (int $errno, string $errstr) use (&$gdWarning): bool {
            $gdWarning = $errstr;

            return true;
        });
        try {
            $image = imagecreatefromstring($data);
        } finally {
            restore_error_handler();
        }
        if (false === $image) {
            throw new ImageProcessingException(sprintf(
                'GD could not load image from data for path: %s%s',
                $path,
                null !== $gdWarning ? ' ('.$gdWarning.')' : ''
            ));
        }

        $width = imagesx($image);
        $height = imagesy($image);

        [$targetWidth, $targetHeight] = self::calculateTargetDimensions($width, $height);

        $resizedImage = imagecreatetruecolor($targetWidth, $targetHeight);
        // Preserve the alpha channel through the resample step instead of
        // compositing transparent areas onto an opaque black canvas.
        imagealphablending($resizedImage, false);
        imagesavealpha($resizedImage, true);
        imagecopyresampled($resizedImage, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        $pixels = [];
        for ($y = 0; $y < $targetHeight; ++$y) {
            $row = [];
            for ($x = 0; $x < $targetWidth; ++$x) {
                $row[] = self::blendAlpha(imagecolorat($resizedImage, $x, $y));
            }
            $pixels[] = $row;
        }

        $hash = Blurhash::encode($pixels, $this->componentsX, $this->componentsY);

        return new ImageMetadata(
            $hash,
            $width,
            $height
        );
    }

    /**
     * @return array{0: int, 1: int}
     */
    private static function calculateTargetDimensions(int $width, int $height): array
    {
        if ($width < 1 || $height < 1) {
            throw new ImageProcessingException(sprintf(
                'Cannot compute thumbnail dimensions for a degenerate image (width=%d, height=%d).',
                $width,
                $height
            ));
        }

        if ($width > $height) {
            return [64, (int) round($height * (64 / $width))];
        }

        return [(int) round($width * (64 / $height)), 64];
    }

    /**
     * Blends a GD truecolor+alpha pixel onto a neutral white background so that
     * transparent/semi-transparent areas contribute a neutral color to the
     * Blurhash instead of a dark halo from the discarded alpha channel.
     *
     * @return array{0: int, 1: int, 2: int}
     */
    private static function blendAlpha(int $rgba): array
    {
        $alpha = ($rgba >> 24) & 0x7F;
        $r = ($rgba >> 16) & 0xFF;
        $g = ($rgba >> 8) & 0xFF;
        $b = $rgba & 0xFF;

        if (0 === $alpha) {
            return [$r, $g, $b];
        }

        $opacity = 1 - ($alpha / 127);

        return [
            (int) round($r * $opacity + 255 * (1 - $opacity)),
            (int) round($g * $opacity + 255 * (1 - $opacity)),
            (int) round($b * $opacity + 255 * (1 - $opacity)),
        ];
    }
}
