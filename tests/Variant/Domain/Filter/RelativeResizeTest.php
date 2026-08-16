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
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\RelativeResize;

final class RelativeResizeTest extends TestCase
{
    public function testImplementsFilter(): void
    {
        self::assertInstanceOf(Filter::class, new RelativeResize(widthPercent: 50.0));
    }

    public function testCanonicalWithBothDimensions(): void
    {
        $filter = new RelativeResize(widthPercent: 50.0, heightPercent: 150.0);

        self::assertSame(['relative_resize' => ['width_percent' => 50.0, 'height_percent' => 150.0]], $filter->canonical());
    }

    public function testCanonicalWithOnlyWidthPercent(): void
    {
        $filter = new RelativeResize(widthPercent: 75.0);

        self::assertSame(['relative_resize' => ['width_percent' => 75.0, 'height_percent' => null]], $filter->canonical());
    }

    public function testThrowsWhenNeitherDimensionIsGiven(): void
    {
        $this->expectException(InvalidFilterDefinition::class);

        new RelativeResize();
    }

    #[DataProvider('nonPositiveProvider')]
    public function testThrowsForNonPositivePercentages(?float $widthPercent, ?float $heightPercent): void
    {
        $this->expectException(InvalidFilterDefinition::class);

        new RelativeResize($widthPercent, $heightPercent);
    }

    public static function nonPositiveProvider(): iterable
    {
        yield 'zero width' => [0.0, null];
        yield 'negative width' => [-10.0, null];
        yield 'zero height' => [null, 0.0];
        yield 'negative height' => [null, -5.0];
    }
}
