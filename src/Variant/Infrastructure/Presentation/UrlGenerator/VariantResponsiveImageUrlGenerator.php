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

namespace Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Presentation\UrlGenerator;

use Tito10047\ProgressiveImageBundle\Service\MetadataReaderInterface;
use Tito10047\ProgressiveImageBundle\UrlGenerator\ResponsiveImageUrlGeneratorInterface;
use Tito10047\ProgressiveImageBundle\Variant\Application\Handler\ResolveVariantUrlHandler;
use Tito10047\ProgressiveImageBundle\Variant\Application\Query\ResolveVariantUrl;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\Dimensions;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\PointOfInterest;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\SourcePath;

/**
 * Implements the existing ResponsiveImageUrlGeneratorInterface — the anti-corruption seam
 * from the DDD plan's context map. The Rendering context (Twig component,
 * ResponsiveAttributeGenerator) is completely unaware this now runs the Variant pipeline
 * instead of Liip.
 *
 * The interface doesn't carry original dimensions, so — unlike the old
 * LiipImagineResponsiveImageUrlGenerator, which silently never ran its POI crop math
 * because it always passed null for them — this reads them via MetadataReader, and only
 * when a pointInterest is actually given (an extra cache lookup would otherwise run on
 * every single image).
 */
final readonly class VariantResponsiveImageUrlGenerator implements ResponsiveImageUrlGeneratorInterface
{
    public function __construct(
        private ResolveVariantUrlHandler $resolveHandler,
        private MetadataReaderInterface $metadataReader,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function generateUrl(string $path, int $targetW, ?int $targetH = null, ?string $pointInterest = null, array $context = []): string
    {
        $targetH ??= $targetW;

        $filterSet = null;
        if (isset($context['filter']) && is_string($context['filter'])) {
            $filterSet = $context['filter'];
            unset($context['filter']);
        }

        $poi = null;
        $originalDimensions = null;
        if (null !== $pointInterest) {
            [$poiX, $poiY] = explode('x', $pointInterest, 2);
            $poi = new PointOfInterest((int) $poiX, (int) $poiY);

            $metadata = $this->metadataReader->getMetadata($path);
            $originalDimensions = new Dimensions($metadata->width, $metadata->height);
        }

        $resolved = ($this->resolveHandler)(new ResolveVariantUrl(
            new SourcePath($path),
            $targetW,
            $targetH,
            $filterSet,
            $poi,
            $originalDimensions,
            $context
        ));

        return $resolved->url;
    }
}
