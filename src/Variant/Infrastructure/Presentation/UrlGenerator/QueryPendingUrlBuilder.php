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

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Tito10047\ProgressiveImageBundle\Variant\Application\Port\PendingUrlBuilder;
use Tito10047\ProgressiveImageBundle\Variant\Application\Query\ResolveVariantUrl;

/**
 * Encodes the original query as explicit, readable route parameters — not a serialized
 * blob — so ImageVariantController can rebuild the exact same VariantSpec via
 * VariantSpecFactory::create(), the same way this bundle's old runtime-filter controller
 * rebuilt its filter config from named query parameters.
 */
final readonly class QueryPendingUrlBuilder implements PendingUrlBuilder
{
    public function __construct(private UrlGeneratorInterface $urlGenerator)
    {
    }

    public function build(ResolveVariantUrl $query): string
    {
        $params = [
            'source' => $query->source->value,
            'width' => $query->width,
            'height' => $query->height,
        ];

        if (null !== $query->filterSet) {
            $params['filterSet'] = $query->filterSet;
        }

        if (null !== $query->poi) {
            $params['poiX'] = $query->poi->x;
            $params['poiY'] = $query->poi->y;
        }

        if (null !== $query->originalDimensions) {
            $params['origW'] = $query->originalDimensions->width;
            $params['origH'] = $query->originalDimensions->height;
        }

        if ([] !== $query->context) {
            $params['context'] = json_encode($query->context, JSON_THROW_ON_ERROR);
        }

        return $this->urlGenerator->generate('pgi_variant_serve', $params, UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
