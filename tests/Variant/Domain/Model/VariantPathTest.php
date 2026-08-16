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

    public function testFromRawWrapsAnAlreadyKnownStoredPathVerbatim(): void
    {
        $path = VariantPath::fromRaw('jpeg/ab/abcdef/uploads/hero.jpg.jpg');

        self::assertSame('jpeg/ab/abcdef/uploads/hero.jpg.jpg', $path->value);
    }

    public function testBelongsToSourceIsTrueForAVariantOfThatExactSource(): void
    {
        $id = new VariantId('abcdef0123456789');
        $source = new SourcePath('uploads/hero.jpg');
        $path = VariantPath::for($id, $source, OutputFormat::Webp);

        self::assertTrue(VariantPath::belongsToSource($path->value, $source));
    }

    public function testBelongsToSourceIsTrueRegardlessOfWhichFormatOrId(): void
    {
        $source = new SourcePath('uploads/hero.jpg');

        $jpeg = VariantPath::for(new VariantId('aaaa0000'), $source, OutputFormat::Jpeg);
        $avif = VariantPath::for(new VariantId('bbbb1111'), $source, OutputFormat::Avif);

        self::assertTrue(VariantPath::belongsToSource($jpeg->value, $source));
        self::assertTrue(VariantPath::belongsToSource($avif->value, $source));
    }

    public function testBelongsToSourceIsFalseForADifferentSource(): void
    {
        $id = new VariantId('abcdef0123456789');
        $path = VariantPath::for($id, new SourcePath('uploads/hero.jpg'), OutputFormat::Webp);

        self::assertFalse(VariantPath::belongsToSource($path->value, new SourcePath('uploads/other.jpg')));
    }

    public function testBelongsToSourceIsFalseForASourceThatIsAPrefixOfAnother(): void
    {
        // "hero.jpg" must not match a variant of "hero.jpg.evil" (or vice versa) just
        // because one string is a substring of the other.
        $id = new VariantId('abcdef0123456789');
        $path = VariantPath::for($id, new SourcePath('uploads/hero.jpg.evil'), OutputFormat::Webp);

        self::assertFalse(VariantPath::belongsToSource($path->value, new SourcePath('uploads/hero.jpg')));
    }

    public function testBelongsToSourceIsFalseForAMalformedPath(): void
    {
        self::assertFalse(VariantPath::belongsToSource('not-a-variant-path', new SourcePath('uploads/hero.jpg')));
    }
}
