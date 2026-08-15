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

use PHPUnit\Framework\TestCase;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\OutputFormat;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\SourcePath;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\VariantId;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\VariantPath;

final class VariantPathTest extends TestCase
{
    public function testBuildsShardedLayoutFromIdSourceAndFormat(): void
    {
        $id = new VariantId('3fk9AbCdEfGhIjKlMnOpQrStUvWxYzQw');
        $source = new SourcePath('uploads/hero.jpg');

        $path = VariantPath::for($id, $source, OutputFormat::Avif);

        self::assertSame('avif/3f/3fk9AbCdEfGhIjKlMnOpQrStUvWxYzQw/uploads/hero.jpg.avif', $path->value);
        self::assertSame($path->value, (string) $path);
    }

    public function testUsesFirstTwoHashCharsAsShard(): void
    {
        $id = new VariantId('zzTopHashValue');
        $source = new SourcePath('a.png');

        $path = VariantPath::for($id, $source, OutputFormat::Png);

        self::assertStringStartsWith('png/zz/', $path->value);
    }

    public function testExtensionMatchesOutputFormat(): void
    {
        $id = new VariantId('hashvalue');
        $source = new SourcePath('a.png');

        $path = VariantPath::for($id, $source, OutputFormat::Jpeg);

        self::assertStringEndsWith('.jpg', $path->value);
    }
}
