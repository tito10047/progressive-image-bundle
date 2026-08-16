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
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\Filter;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\Rotate;

final class RotateTest extends TestCase
{
    public function testImplementsFilter(): void
    {
        self::assertInstanceOf(Filter::class, new Rotate(90));
    }

    #[DataProvider('normalizationProvider')]
    public function testNormalizesDegreesToZeroToThreeSixtyRange(int $input, int $expected): void
    {
        $rotate = new Rotate($input);

        self::assertSame($expected, $rotate->degrees);
        self::assertSame(['rotate' => ['degrees' => $expected]], $rotate->canonical());
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function normalizationProvider(): iterable
    {
        yield 'zero' => [0, 0];
        yield 'plain 90' => [90, 90];
        yield 'full turn plus 90' => [450, 90];
        yield 'exact full turn' => [360, 0];
        yield 'negative 90 wraps to 270' => [-90, 270];
        yield 'negative full turn' => [-360, 0];
    }
}
