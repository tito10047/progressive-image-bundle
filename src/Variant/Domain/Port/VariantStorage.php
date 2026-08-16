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

namespace Tito10047\ProgressiveImageBundle\Variant\Domain\Port;

use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\GeneratedImage;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\VariantPath;

/**
 * Storage of generated variants — the "Variant Store", never called "cache" (see the
 * ubiquitous language in the DDD plan: cache means HTTP/fragment cache, a different thing).
 */
interface VariantStorage
{
    /**
     * @phpstan-impure reflects external, mutable filesystem state — the same $path can
     * legitimately answer differently across two calls within a single request (e.g.
     * ResolveVariantUrlHandler re-checking after a synchronous dispatch()).
     */
    public function exists(VariantPath $path): bool;

    public function write(VariantPath $path, GeneratedImage $image): void;

    public function read(VariantPath $path): GeneratedImage;

    public function delete(VariantPath $path): void;

    public function publicPath(VariantPath $path): string;

    public function writeFailMarker(VariantPath $path, \DateTimeImmutable $at): void;

    public function failMarkerTimestamp(VariantPath $path): ?\DateTimeImmutable;
}
