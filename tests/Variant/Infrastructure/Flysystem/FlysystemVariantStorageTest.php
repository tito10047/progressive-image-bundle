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

namespace Tito10047\ProgressiveImageBundle\Tests\Variant\Infrastructure\Flysystem;

use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnableToMoveFile;
use PHPUnit\Framework\TestCase;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Exception\VariantDomainException;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\GeneratedImage;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\OutputFormat;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\SourcePath;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\VariantId;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\VariantPath;
use Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Flysystem\FlysystemVariantStorage;

final class FlysystemVariantStorageTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/pgi-fvs-test-'.bin2hex(random_bytes(8));
        mkdir($this->root, 0o777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $entries = scandir($dir) ?: [];
        foreach ($entries as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }

            $path = $dir.'/'.$entry;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }

        rmdir($dir);
    }

    private function makePath(string $sourceValue = 'uploads/hero.jpg', OutputFormat $format = OutputFormat::Webp): VariantPath
    {
        return VariantPath::for(new VariantId('abcdef0123456789'), new SourcePath($sourceValue), $format);
    }

    /**
     * VariantPath's constructor is private specifically so its first path segment can only
     * ever be a real OutputFormat::value (see VariantPath::for()) — reflection is the only
     * way to construct one with an invalid format segment, to exercise formatOf()'s
     * defensive handling of that otherwise-unreachable state.
     */
    private function makePathWithRawValue(string $value): VariantPath
    {
        $ref = new \ReflectionClass(VariantPath::class);
        $instance = $ref->newInstanceWithoutConstructor();
        $ref->getProperty('value')->setValue($instance, $value);

        return $instance;
    }

    public function testWriteCleansUpTheTmpFileWhenMoveFails(): void
    {
        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->expects($this->once())->method('write');
        $filesystem->expects($this->once())
            ->method('move')
            ->willThrowException(new UnableToMoveFile());
        $filesystem->expects($this->once())->method('delete');

        $storage = new FlysystemVariantStorage($filesystem);

        $this->expectException(UnableToMoveFile::class);
        $storage->write($this->makePath(), new GeneratedImage('data', OutputFormat::Webp));
    }

    public function testWriteFailMarkerIsWrittenAtomicallyViaTmpAndMove(): void
    {
        $filesystem = new Filesystem(new LocalFilesystemAdapter($this->root));
        $storage = new FlysystemVariantStorage($filesystem);
        $path = $this->makePath();

        $storage->writeFailMarker($path, new \DateTimeImmutable('@1000'));

        $timestamp = $storage->failMarkerTimestamp($path);
        self::assertNotNull($timestamp);
        self::assertSame(1000, $timestamp->getTimestamp());

        // No leftover .tmp-* files after a successful write.
        $entries = $filesystem->listContents('', true)->toArray();
        $tmpFiles = array_filter($entries, static fn ($e) => str_contains($e->path(), '.tmp-'));
        self::assertSame([], array_values($tmpFiles));
    }

    public function testFailMarkerTimestampReturnsNullForCorruptedMarkerContentInsteadOfEpochZero(): void
    {
        $filesystem = new Filesystem(new LocalFilesystemAdapter($this->root));
        $storage = new FlysystemVariantStorage($filesystem);
        $path = $this->makePath();

        // Simulate a corrupted/partial marker file directly (bypassing writeFailMarker()).
        $filesystem->write($path->value.'.failed', 'not-a-timestamp');

        self::assertNull($storage->failMarkerTimestamp($path));
    }

    public function testPublicPathEncodesSpecialCharactersInEachSegment(): void
    {
        $filesystem = new Filesystem(new LocalFilesystemAdapter($this->root));
        $storage = new FlysystemVariantStorage($filesystem, publicUrlPrefix: '/media/pgi');

        $path = $this->makePath('uploads/my photo #1?.jpg');

        self::assertStringContainsString(rawurlencode('my photo #1?.jpg'), $storage->publicPath($path));
        self::assertStringStartsWith('/media/pgi/webp/', $storage->publicPath($path));
        self::assertStringNotContainsString(' ', $storage->publicPath($path));
        self::assertStringNotContainsString('#', $storage->publicPath($path));
    }

    public function testReadThrowsDomainExceptionForACorruptedFormatSegmentInsteadOfLeakingValueError(): void
    {
        $filesystem = new Filesystem(new LocalFilesystemAdapter($this->root));
        $storage = new FlysystemVariantStorage($filesystem);

        $path = $this->makePathWithRawValue('not-a-format/abc.jpg');
        $filesystem->write($path->value, 'data');

        $this->expectException(VariantDomainException::class);
        $storage->read($path);
    }
}
