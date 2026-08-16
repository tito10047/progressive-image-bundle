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

namespace Tito10047\ProgressiveImageBundle\Tests\Variant\Contract;

use PHPUnit\Framework\TestCase;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\GeneratedImage;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\OutputFormat;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\SourcePath;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\VariantId;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\VariantPath;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Port\VariantStorage;

/**
 * One suite, run against every VariantStorage implementation (InMemory fake and every real
 * adapter) — a fake that passes this but doesn't behave like the real thing would be a lie
 * the Application-layer tests silently rely on.
 */
abstract class VariantStorageContractTestCase extends TestCase
{
    abstract protected function createStorage(): VariantStorage;

    private function makePath(string $suffix = ''): VariantPath
    {
        return VariantPath::for(
            new VariantId('abcdef0123456789'.$suffix),
            new SourcePath('uploads/hero.jpg'),
            OutputFormat::Webp
        );
    }

    public function testExistsIsFalseForAnUnwrittenPath(): void
    {
        self::assertFalse($this->createStorage()->exists($this->makePath()));
    }

    public function testWriteMakesExistsTrue(): void
    {
        $storage = $this->createStorage();
        $path = $this->makePath();

        $storage->write($path, new GeneratedImage('bytes', OutputFormat::Webp));

        self::assertTrue($storage->exists($path));
    }

    public function testReadReturnsWhatWasWritten(): void
    {
        $storage = $this->createStorage();
        // The format written must match the path's own format segment — VariantPath::for()
        // and GeneratedImage are always derived from the same VariantSpec in real usage.
        $path = VariantPath::for(new VariantId('abcdef0123456789'), new SourcePath('uploads/hero.jpg'), OutputFormat::Avif);

        $storage->write($path, new GeneratedImage('the-image-bytes', OutputFormat::Avif));
        $read = $storage->read($path);

        self::assertSame('the-image-bytes', $read->contents);
        self::assertSame(OutputFormat::Avif, $read->format);
    }

    public function testDeleteRemovesTheVariant(): void
    {
        $storage = $this->createStorage();
        $path = $this->makePath();
        $storage->write($path, new GeneratedImage('bytes', OutputFormat::Webp));

        $storage->delete($path);

        self::assertFalse($storage->exists($path));
    }

    public function testDeletingAnUnwrittenPathDoesNotThrow(): void
    {
        $storage = $this->createStorage();

        $storage->delete($this->makePath());

        self::assertFalse($storage->exists($this->makePath()));
    }

    public function testPublicPathIncludesTheVariantPathValue(): void
    {
        $storage = $this->createStorage();
        $path = $this->makePath();

        self::assertStringContainsString($path->value, $storage->publicPath($path));
    }

    public function testFailMarkerTimestampIsNullWhenNoneWasWritten(): void
    {
        self::assertNull($this->createStorage()->failMarkerTimestamp($this->makePath()));
    }

    public function testFailMarkerRoundTripsTheExactTimestamp(): void
    {
        $storage = $this->createStorage();
        $path = $this->makePath();
        $at = new \DateTimeImmutable('2026-01-15T10:30:00+00:00');

        $storage->writeFailMarker($path, $at);
        $read = $storage->failMarkerTimestamp($path);

        self::assertNotNull($read);
        self::assertSame($at->getTimestamp(), $read->getTimestamp());
    }

    public function testDifferentPathsDoNotInterfereWithEachOther(): void
    {
        $storage = $this->createStorage();
        $a = $this->makePath('-a');
        $b = $this->makePath('-b');

        $storage->write($a, new GeneratedImage('a-bytes', OutputFormat::Webp));

        self::assertTrue($storage->exists($a));
        self::assertFalse($storage->exists($b));
    }

    public function testListReturnsEmptyForASourceWithNoVariants(): void
    {
        $storage = $this->createStorage();

        self::assertSame([], iterator_to_array($storage->list(new SourcePath('uploads/hero.jpg')), false));
    }

    public function testListReturnsEveryVariantWrittenForASourceAcrossFormatsAndIds(): void
    {
        $storage = $this->createStorage();
        $source = new SourcePath('uploads/hero.jpg');
        $jpeg = VariantPath::for(new VariantId('aaaa0000'), $source, OutputFormat::Jpeg);
        $webp = VariantPath::for(new VariantId('bbbb1111'), $source, OutputFormat::Webp);

        $storage->write($jpeg, new GeneratedImage('jpeg-bytes', OutputFormat::Jpeg));
        $storage->write($webp, new GeneratedImage('webp-bytes', OutputFormat::Webp));

        $listed = array_map(static fn (VariantPath $p): string => $p->value, iterator_to_array($storage->list($source), false));
        sort($listed);
        $expected = [$jpeg->value, $webp->value];
        sort($expected);

        self::assertSame($expected, $listed);
    }

    public function testListDoesNotReturnVariantsBelongingToADifferentSource(): void
    {
        $storage = $this->createStorage();
        $sourceA = new SourcePath('uploads/a.jpg');
        $sourceB = new SourcePath('uploads/b.jpg');
        $pathA = VariantPath::for(new VariantId('aaaa0000'), $sourceA, OutputFormat::Jpeg);
        $pathB = VariantPath::for(new VariantId('bbbb1111'), $sourceB, OutputFormat::Jpeg);

        $storage->write($pathA, new GeneratedImage('a-bytes', OutputFormat::Jpeg));
        $storage->write($pathB, new GeneratedImage('b-bytes', OutputFormat::Jpeg));

        $listed = iterator_to_array($storage->list($sourceA), false);

        self::assertCount(1, $listed);
        self::assertSame($pathA->value, $listed[0]->value);
    }

    public function testListedPathsCanBeDeletedDirectly(): void
    {
        $storage = $this->createStorage();
        $source = new SourcePath('uploads/hero.jpg');
        $path = VariantPath::for(new VariantId('aaaa0000'), $source, OutputFormat::Jpeg);
        $storage->write($path, new GeneratedImage('bytes', OutputFormat::Jpeg));

        foreach ($storage->list($source) as $listedPath) {
            $storage->delete($listedPath);
        }

        self::assertFalse($storage->exists($path));
        self::assertSame([], iterator_to_array($storage->list($source), false));
    }
}
