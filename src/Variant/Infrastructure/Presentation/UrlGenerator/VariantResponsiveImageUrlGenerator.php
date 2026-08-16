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
use Tito10047\ProgressiveImageBundle\UrlGenerator\ResponsiveImageUrlGeneratorInterface;
use Tito10047\ProgressiveImageBundle\Variant\Application\Handler\ResolveVariantUrlHandler;
use Tito10047\ProgressiveImageBundle\Variant\Application\Query\ResolveVariantUrl;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Exception\InvalidFilterDefinition;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\OutputFormat;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\PointOfInterest;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\SourcePath;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Port\SourceReader;

/**
 * Implements the existing ResponsiveImageUrlGeneratorInterface — the anti-corruption seam
 * from the DDD plan's context map. The Rendering context (Twig component,
 * ResponsiveAttributeGenerator) is completely unaware this now runs the Variant pipeline
 * instead of the old runtime-filter pipeline.
 *
 * The interface doesn't carry original dimensions, so — unlike the previous generator,
 * which silently never ran its POI crop math because it always passed null for them —
 * this reads them via SourceReader (the same cheap getimagesize()-based lookup the Variant
 * pipeline already uses elsewhere), and only when a pointInterest is actually given. This
 * deliberately does NOT go through MetadataReaderInterface/the legacy analyzer pipeline:
 * that would also compute a blurhash on every cache miss, which is never used here.
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
        private SourceReader $sourceReader,
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
            $parts = explode('x', $pointInterest, 2);
            if (2 !== count($parts) || !is_numeric($parts[0]) || !is_numeric($parts[1])) {
                throw new InvalidFilterDefinition(sprintf('Invalid pointInterest "%s", expected format "X0xY0" e.g. "500x500".', $pointInterest));
            }
            $poi = new PointOfInterest((int) $parts[0], (int) $parts[1]);

            $source = $this->sourceReader->read(new SourcePath($path));
            if (is_resource($source->stream)) {
                fclose($source->stream);
            }
            $originalDimensions = $source->dimensions;
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
            if ($this->acceptsMime($accept, OutputFormat::from($format)->mime())) {
                return $format;
            }
        }

        return null;
    }

    /**
     * Minimal RFC 7231 Accept-header matching: media-range wildcards (e.g. "image/star" or
     * "star/star") and "q=0" exclusions, in addition to an exact mime match. Not a full
     * content-negotiation implementation (e.g. it doesn't compare q-values across different
     * candidate formats) — $this->negotiateFormats is already the server's own preference
     * order, so the first format the client accepts at all (q > 0) wins.
     */
    private function acceptsMime(string $accept, string $mime): bool
    {
        [$type] = explode('/', $mime, 2);

        foreach (explode(',', $accept) as $entry) {
            $parts = explode(';', trim($entry));
            $range = trim($parts[0]);
            if ('' === $range) {
                continue;
            }

            $q = 1.0;
            foreach (array_slice($parts, 1) as $param) {
                $param = trim($param);
                if (str_starts_with($param, 'q=') && is_numeric(substr($param, 2))) {
                    $q = (float) substr($param, 2);
                }
            }

            if ($q <= 0.0) {
                continue;
            }

            if ('*/*' === $range || $mime === $range || $type.'/*' === $range) {
                return true;
            }
        }

        return false;
    }
}
