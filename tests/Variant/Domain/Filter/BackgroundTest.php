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
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\Background;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\Filter;

final class BackgroundTest extends TestCase
{
    #[DataProvider('validColorProvider')]
    public function testAcceptsValidHexColors(string $color): void
    {
        $background = new Background($color);

        self::assertInstanceOf(Filter::class, $background);
        self::assertSame(strtolower($color), $background->color);
        self::assertSame(['background' => ['color' => strtolower($color)]], $background->canonical());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validColorProvider(): iterable
    {
        yield 'short white' => ['#ffffff'];
        yield 'short black uppercase' => ['#000000'];
        yield 'with alpha' => ['#ff00ffaa'];
    }

    #[DataProvider('invalidColorProvider')]
    public function testRejectsInvalidColors(string $color): void
    {
        $this->expectException(InvalidFilterDefinition::class);

        new Background($color);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidColorProvider(): iterable
    {
        yield 'missing hash' => ['ffffff'];
        yield 'too short' => ['#fff'];
        yield 'invalid chars' => ['#gggggg'];
        yield 'empty' => [''];
    }
}
