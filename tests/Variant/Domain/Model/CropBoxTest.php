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
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\CropBox;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\Dimensions;

final class CropBoxTest extends TestCase
{
    public function testConstructsWithNonNegativeStartAndSize(): void
    {
        $box = new CropBox(10, 20, new Dimensions(100, 50));

        self::assertSame(10, $box->startX);
        self::assertSame(20, $box->startY);
        self::assertSame(100, $box->size->width);
        self::assertSame(50, $box->size->height);
    }

    #[DataProvider('negativeStartProvider')]
    public function testRejectsNegativeStart(int $startX, int $startY): void
    {
        $this->expectException(InvalidFilterDefinition::class);

        new CropBox($startX, $startY, new Dimensions(10, 10));
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function negativeStartProvider(): iterable
    {
        yield 'negative startX' => [-1, 0];
        yield 'negative startY' => [0, -1];
    }

    public function testWithinAcceptsBoxFittingInsideBounds(): void
    {
        $box = CropBox::within(10, 10, new Dimensions(80, 80), new Dimensions(100, 100));

        self::assertSame(10, $box->startX);
        self::assertSame(10, $box->startY);
    }

    public function testWithinAcceptsBoxExactlyMatchingBounds(): void
    {
        $box = CropBox::within(0, 0, new Dimensions(100, 100), new Dimensions(100, 100));

        self::assertSame(100, $box->size->width);
    }

    #[DataProvider('outOfBoundsProvider')]
    public function testWithinRejectsBoxExceedingBounds(int $startX, int $startY, Dimensions $size, Dimensions $bounds): void
    {
        $this->expectException(InvalidFilterDefinition::class);

        CropBox::within($startX, $startY, $size, $bounds);
    }

    /**
     * @return iterable<string, array{int, int, Dimensions, Dimensions}>
     */
    public static function outOfBoundsProvider(): iterable
    {
        yield 'exceeds width' => [50, 0, new Dimensions(60, 10), new Dimensions(100, 100)];
        yield 'exceeds height' => [0, 50, new Dimensions(10, 60), new Dimensions(100, 100)];
        yield 'start beyond bounds' => [150, 0, new Dimensions(10, 10), new Dimensions(100, 100)];
    }
}
