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
}
