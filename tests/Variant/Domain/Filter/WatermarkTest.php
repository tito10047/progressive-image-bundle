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

namespace Tito10047\ProgressiveImageBundle\Tests\Variant\Domain\Filter;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Exception\InvalidFilterDefinition;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\Filter;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\Watermark;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\WatermarkPosition;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\SourcePath;

final class WatermarkTest extends TestCase
{
    public function testDefaultsToFullOpacity(): void
    {
        $watermark = new Watermark(new SourcePath('logos/brand.png'), WatermarkPosition::BottomRight);

        self::assertInstanceOf(Filter::class, $watermark);
        self::assertSame(100, $watermark->opacity);
        self::assertSame(
            ['watermark' => ['image' => 'logos/brand.png', 'position' => 'bottom_right', 'opacity' => 100]],
            $watermark->canonical()
        );
    }

    public function testAcceptsExplicitOpacity(): void
    {
        $watermark = new Watermark(new SourcePath('logos/brand.png'), WatermarkPosition::Center, 40);

        self::assertSame(40, $watermark->opacity);
        self::assertSame('center', $watermark->canonical()['watermark']['position']);
    }

    #[DataProvider('invalidOpacityProvider')]
    public function testRejectsOpacityOutsideZeroToHundred(int $opacity): void
    {
        $this->expectException(InvalidFilterDefinition::class);

        new Watermark(new SourcePath('logos/brand.png'), WatermarkPosition::TopLeft, $opacity);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function invalidOpacityProvider(): iterable
    {
        yield 'negative' => [-1];
        yield 'above 100' => [101];
    }
}
