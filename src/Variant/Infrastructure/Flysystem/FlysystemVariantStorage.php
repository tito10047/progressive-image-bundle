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

namespace Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Flysystem;

use League\Flysystem\FilesystemOperator;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Exception\VariantDomainException;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\GeneratedImage;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\OutputFormat;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\VariantPath;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Port\VariantStorage;

/**
 * The single storage adapter for both local and cloud, per the DDD plan's storage
 * strategy decision — the caller picks the strategy by choosing a FilesystemOperator
 * (LocalFilesystemAdapter, AwsS3V3Adapter, ...) via oneup/flysystem-bundle config, not by
 * picking a different PHP class. Format is not stored separately: VariantPath::for()
 * guarantees the path's first segment is always $format->value, so it's parsed back out
 * on read rather than duplicated in a companion metadata file.
 */
final readonly class FlysystemVariantStorage implements VariantStorage
{
    public function __construct(
        private FilesystemOperator $filesystem,
        private string $prefix = '',
        private string $publicUrlPrefix = '/media/pgi',
    ) {
    }

    public function exists(VariantPath $path): bool
    {
        return $this->filesystem->fileExists($this->fullPath($path));
    }

    public function write(VariantPath $path, GeneratedImage $image): void
    {
        $full = $this->fullPath($path);
        $tmp = $full.'.tmp-'.bin2hex(random_bytes(8));

        $this->filesystem->write($tmp, $image->contents);
        $this->filesystem->move($tmp, $full);
    }

    public function read(VariantPath $path): GeneratedImage
    {
        $full = $this->fullPath($path);

        if (!$this->filesystem->fileExists($full)) {
            throw new VariantDomainException(sprintf('No variant stored at "%s".', $path->value));
        }

        return new GeneratedImage($this->filesystem->read($full), $this->formatOf($path));
    }

    public function delete(VariantPath $path): void
    {
        $this->filesystem->delete($this->fullPath($path));
    }

    public function publicPath(VariantPath $path): string
    {
        return rtrim($this->publicUrlPrefix, '/').'/'.$path->value;
    }

    public function writeFailMarker(VariantPath $path, \DateTimeImmutable $at): void
    {
        $this->filesystem->write($this->failMarkerPath($path), (string) $at->getTimestamp());
    }

    public function failMarkerTimestamp(VariantPath $path): ?\DateTimeImmutable
    {
        $marker = $this->failMarkerPath($path);

        if (!$this->filesystem->fileExists($marker)) {
            return null;
        }

        return (new \DateTimeImmutable())->setTimestamp((int) $this->filesystem->read($marker));
    }

    private function formatOf(VariantPath $path): OutputFormat
    {
        [$formatSegment] = explode('/', $path->value, 2);

        return OutputFormat::from($formatSegment);
    }

    private function fullPath(VariantPath $path): string
    {
        return '' === $this->prefix ? $path->value : rtrim($this->prefix, '/').'/'.$path->value;
    }

    private function failMarkerPath(VariantPath $path): string
    {
        return $this->fullPath($path).'.failed';
    }
}
