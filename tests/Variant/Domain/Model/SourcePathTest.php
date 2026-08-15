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
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\SourcePath;

final class SourcePathTest extends TestCase
{
    public function testKeepsAlreadyNormalizedPathUnchanged(): void
    {
        $path = new SourcePath('uploads/hero.jpg');

        self::assertSame('uploads/hero.jpg', $path->value);
    }

    public function testStripsLeadingSlash(): void
    {
        $path = new SourcePath('/uploads/hero.jpg');

        self::assertSame('uploads/hero.jpg', $path->value);
    }

    public function testTrimsSurroundingWhitespace(): void
    {
        $path = new SourcePath("  uploads/hero.jpg\n");

        self::assertSame('uploads/hero.jpg', $path->value);
    }

    public function testStringCastReturnsNormalizedValue(): void
    {
        $path = new SourcePath('/uploads/hero.jpg');

        self::assertSame('uploads/hero.jpg', (string) $path);
    }

    #[DataProvider('invalidPathProvider')]
    public function testRejectsInvalidPaths(string $value): void
    {
        $this->expectException(InvalidFilterDefinition::class);

        new SourcePath($value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidPathProvider(): iterable
    {
        yield 'empty string' => [''];
        yield 'whitespace only' => ['   '];
        yield 'leading traversal' => ['../etc/passwd'];
        yield 'nested traversal' => ['uploads/../../etc/passwd'];
        yield 'trailing traversal' => ['uploads/..'];
    }
}
