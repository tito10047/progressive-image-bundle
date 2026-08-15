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

namespace Tito10047\ProgressiveImageBundle\Tests\Variant\Domain\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\Dimensions;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\PointOfInterest;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Service\AspectCropCalculator;

/**
 * Pixel-parity dataset ported from the pre-existing Liip integration tests
 * (tests/Integration/Controller/LiipImaginePointInterestTest.php and
 * LiipImagineRuntimeConfigGeneratorTest.php) — same inputs, same expected
 * crop box, so the visual output of the new pipeline is byte-identical
 * to the old one for these scenarios.
 */
final class AspectCropCalculatorTest extends TestCase
{
    #[DataProvider('cropProvider')]
    public function testCalculatesSameCropBoxAsLegacyLiipImplementation(
        int $poiX,
        int $poiY,
        int $targetWidth,
        int $targetHeight,
        int $origWidth,
        int $origHeight,
        int $expectedStartX,
        int $expectedStartY,
        int $expectedCropWidth,
        int $expectedCropHeight,
    ): void {
        $calculator = new AspectCropCalculator();

        $box = $calculator->calculate(
            new PointOfInterest($poiX, $poiY),
            new Dimensions($targetWidth, $targetHeight),
            new Dimensions($origWidth, $origHeight)
        );

        self::assertSame($expectedStartX, $box->startX);
        self::assertSame($expectedStartY, $box->startY);
        self::assertSame($expectedCropWidth, $box->size->width);
        self::assertSame($expectedCropHeight, $box->size->height);
    }

    /**
     * @return iterable<string, array{int, int, int, int, int, int, int, int, int, int}>
     */
    public static function cropProvider(): iterable
    {
        // Landscape 200x100 -> square 100x100, POI (150,50).
        // origRatio=2.0 > targetRatio=1.0 -> constrain by height.
        yield 'landscape original, POI right of centre' => [150, 50, 100, 100, 200, 100, 100, 0, 100, 100];

        // Portrait 100x300 -> square 100x100. origRatio=0.333 < targetRatio=1.0 -> constrain by width.
        yield 'portrait original, POI near top' => [50, 25, 100, 100, 100, 300, 0, 0, 100, 100];
        yield 'portrait original, POI at centre' => [50, 150, 100, 100, 100, 300, 0, 100, 100, 100];
        yield 'portrait original, POI near bottom' => [50, 275, 100, 100, 100, 300, 0, 200, 100, 100];

        // Equal aspect ratio -> else branch (constrain by width), clamp forces full-image crop.
        yield 'equal aspect ratio clamps to full image' => [100, 50, 200, 100, 200, 100, 0, 0, 200, 100];

        // POI at the extreme corner clamps the crop start to zero, not negative.
        // Square 200x200 original -> portrait 50x100 target: origRatio=1.0 > targetRatio=0.5
        // -> constrain by height, cropHeight=200, cropWidth=round(200*50/100)=100.
        yield 'POI at top-left corner clamps to zero' => [0, 0, 50, 100, 200, 200, 0, 0, 100, 200];
    }
}
