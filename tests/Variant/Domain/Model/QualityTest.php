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
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\Quality;

final class QualityTest extends TestCase
{
    #[DataProvider('boundaryProvider')]
    public function testAcceptsValuesWithinInclusiveRange(int $value): void
    {
        $quality = new Quality($value);

        self::assertSame($value, $quality->value);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function boundaryProvider(): iterable
    {
        yield 'lower boundary' => [1];
        yield 'upper boundary' => [100];
        yield 'typical value' => [85];
    }

    #[DataProvider('outOfRangeProvider')]
    public function testRejectsValuesOutsideRange(int $value): void
    {
        $this->expectException(InvalidFilterDefinition::class);

        new Quality($value);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function outOfRangeProvider(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-1];
        yield 'above 100' => [101];
    }
}
