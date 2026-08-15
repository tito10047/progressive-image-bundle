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
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\PointOfInterest;

final class PointOfInterestTest extends TestCase
{
    public function testConstructsWithNonNegativeCoordinates(): void
    {
        $poi = new PointOfInterest(120, 340);

        self::assertSame(120, $poi->x);
        self::assertSame(340, $poi->y);
    }

    public function testAllowsZeroCoordinates(): void
    {
        $poi = new PointOfInterest(0, 0);

        self::assertSame(0, $poi->x);
        self::assertSame(0, $poi->y);
    }

    #[DataProvider('negativeProvider')]
    public function testRejectsNegativeCoordinates(int $x, int $y): void
    {
        $this->expectException(InvalidFilterDefinition::class);

        new PointOfInterest($x, $y);
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function negativeProvider(): iterable
    {
        yield 'negative x' => [-1, 0];
        yield 'negative y' => [0, -1];
        yield 'both negative' => [-5, -5];
    }
}
