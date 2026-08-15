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

namespace Tito10047\ProgressiveImageBundle\Tests\Variant\Double;

use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\GeneratedImage;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\VariantPath;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Port\VariantStorage;

final class InMemoryVariantStorage implements VariantStorage
{
    /** @var array<string, GeneratedImage> */
    private array $files = [];

    /** @var array<string, \DateTimeImmutable> */
    private array $failMarkers = [];

    public function exists(VariantPath $path): bool
    {
        return isset($this->files[$path->value]);
    }

    public function write(VariantPath $path, GeneratedImage $image): void
    {
        $this->files[$path->value] = $image;
    }

    public function read(VariantPath $path): GeneratedImage
    {
        return $this->files[$path->value] ?? throw new \RuntimeException(sprintf('No variant stored at "%s".', $path->value));
    }

    public function delete(VariantPath $path): void
    {
        unset($this->files[$path->value]);
    }

    public function publicPath(VariantPath $path): string
    {
        return '/media/pgi/'.$path->value;
    }

    public function writeFailMarker(VariantPath $path, \DateTimeImmutable $at): void
    {
        $this->failMarkers[$path->value] = $at;
    }

    public function failMarkerTimestamp(VariantPath $path): ?\DateTimeImmutable
    {
        return $this->failMarkers[$path->value] ?? null;
    }
}
