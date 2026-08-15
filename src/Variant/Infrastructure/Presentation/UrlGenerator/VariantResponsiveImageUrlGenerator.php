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

use Symfony\Component\HttpFoundation\RequestStack;
use Tito10047\ProgressiveImageBundle\Service\MetadataReaderInterface;
use Tito10047\ProgressiveImageBundle\UrlGenerator\ResponsiveImageUrlGeneratorInterface;
use Tito10047\ProgressiveImageBundle\Variant\Application\Handler\ResolveVariantUrlHandler;
use Tito10047\ProgressiveImageBundle\Variant\Application\Query\ResolveVariantUrl;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\Dimensions;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\OutputFormat;
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
 *
 * Format negotiation (formats.negotiate in the bundle config) also lives here rather than
 * in VariantSpecFactory: it just sets context['format']/context['quality'], the same
 * override path a caller could set explicitly — an explicit context format always wins
 * over negotiation, never the other way round.
 */
final readonly class VariantResponsiveImageUrlGenerator implements ResponsiveImageUrlGeneratorInterface
{
    /**
     * @param list<string>       $negotiateFormats formats tried in order against the Accept header
     * @param array<string, int> $qualityByFormat
     */
    public function __construct(
        private ResolveVariantUrlHandler $resolveHandler,
        private MetadataReaderInterface $metadataReader,
        private RequestStack $requestStack,
        private array $negotiateFormats = [],
        private array $qualityByFormat = [],
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

        if (!array_key_exists('format', $context)) {
            $negotiated = $this->negotiateFormat();
            if (null !== $negotiated) {
                $context['format'] = $negotiated;
                if (isset($this->qualityByFormat[$negotiated])) {
                    $context['quality'] = $this->qualityByFormat[$negotiated];
                }
            }
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

    private function negotiateFormat(): ?string
    {
        if ([] === $this->negotiateFormats) {
            return null;
        }

        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return null;
        }

        $accept = $request->headers->get('Accept', '');
        foreach ($this->negotiateFormats as $format) {
            if (str_contains($accept, OutputFormat::from($format)->mime())) {
                return $format;
            }
        }

        return null;
    }
}
