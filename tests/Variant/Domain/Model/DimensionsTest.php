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

namespace Tito10047\ProgressiveImageBundle\Tests\Variant\Domain\Model;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Exception\InvalidFilterDefinition;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\Dimensions;

final class DimensionsTest extends TestCase
{
    public function testConstructsWithPositiveWidthAndHeight(): void
    {
        $dimensions = new Dimensions(200, 100);

        self::assertSame(200, $dimensions->width);
        self::assertSame(100, $dimensions->height);
    }

    #[DataProvider('invalidDimensionsProvider')]
    public function testRejectsNonPositiveDimensions(int $width, int $height): void
    {
        $this->expectException(InvalidFilterDefinition::class);

        new Dimensions($width, $height);
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function invalidDimensionsProvider(): iterable
    {
        yield 'zero width' => [0, 100];
        yield 'zero height' => [100, 0];
        yield 'negative width' => [-1, 100];
        yield 'negative height' => [100, -1];
        yield 'both zero' => [0, 0];
    }

    public function testAspectRatioIsWidthDividedByHeight(): void
    {
        $dimensions = new Dimensions(1920, 1080);

        self::assertEqualsWithDelta(1920 / 1080, $dimensions->aspectRatio(), 0.0000001);
    }

    public function testIsWiderThanComparesAspectRatios(): void
    {
        $landscape = new Dimensions(1920, 1080);
        $portrait = new Dimensions(1080, 1920);
        $square = new Dimensions(500, 500);

        self::assertTrue($landscape->isWiderThan($portrait));
        self::assertFalse($portrait->isWiderThan($landscape));
        self::assertFalse($landscape->isWiderThan($landscape));
        self::assertTrue($landscape->isWiderThan($square));
    }
}
