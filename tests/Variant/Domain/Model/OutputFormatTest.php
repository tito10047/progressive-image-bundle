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
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\OutputFormat;

final class OutputFormatTest extends TestCase
{
    #[DataProvider('formatProvider')]
    public function testMimeType(OutputFormat $format, string $expectedMime, string $expectedExtension): void
    {
        self::assertSame($expectedMime, $format->mime());
    }

    #[DataProvider('formatProvider')]
    public function testExtension(OutputFormat $format, string $expectedMime, string $expectedExtension): void
    {
        self::assertSame($expectedExtension, $format->extension());
    }

    /**
     * @return iterable<string, array{OutputFormat, string, string}>
     */
    public static function formatProvider(): iterable
    {
        yield 'jpeg' => [OutputFormat::Jpeg, 'image/jpeg', 'jpg'];
        yield 'png' => [OutputFormat::Png, 'image/png', 'png'];
        yield 'webp' => [OutputFormat::Webp, 'image/webp', 'webp'];
        yield 'avif' => [OutputFormat::Avif, 'image/avif', 'avif'];
    }
}
