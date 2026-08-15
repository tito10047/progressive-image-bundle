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

namespace Tito10047\ProgressiveImageBundle\Variant\Application\Service;

use Tito10047\ProgressiveImageBundle\Variant\Domain\Exception\InvalidFilterDefinition;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\Crop;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\FilterChain;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\Thumbnail;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\Dimensions;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\OutputFormat;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\PointOfInterest;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\Quality;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\VariantSpec;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Service\AspectCropCalculator;

/**
 * Replaces the bundle's old runtime filter-config generator. Merge order is unchanged from
 * that legacy generator — filter set, then imageConfigs, then per-call context, each overriding the
 * previous recursively — but the merged raw arrays are turned into a typed FilterChain
 * instead of staying a flat array, and the sizing crop/thumbnail is always the chain's
 * own construction, never a leftover from the merge (see the crop-before-thumbnail note
 * on VariantSpecFactory::create()).
 */
final readonly class VariantSpecFactory
{
    /**
     * @param array<string, mixed> $imageConfigs
     */
    public function __construct(
        private FilterSetRegistry $filterSets,
        private FilterFactory $filterFactory,
        private AspectCropCalculator $cropCalculator,
        private array $imageConfigs = [],
        private OutputFormat $defaultFormat = OutputFormat::Jpeg,
        private Quality $defaultQuality = new Quality(85),
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function create(
        int $width,
        int $height,
        ?string $filterSetName = null,
        ?PointOfInterest $poi = null,
        ?Dimensions $originalDimensions = null,
        array $context = [],
    ): VariantSpec {
        $filterSetRaw = null !== $filterSetName ? $this->filterSets->rawFilterSet($filterSetName) : [];

        $merged = self::mergeLayers($filterSetRaw, $this->imageConfigs, $context);

        $chain = $this->parseFilters($merged)->without(Crop::class, Thumbnail::class);

        // The target size always wins: any crop/thumbnail contributed by the filter set,
        // imageConfigs or context is discarded above and replaced by the pair that
        // actually matches (width, height, poi) — crop always precedes thumbnail so a POI
        // crop is never overridden by a stray "centre" thumbnail (regression: f3e55c3).
        if (null !== $poi && null !== $originalDimensions) {
            $target = new Dimensions($width, $height);
            $cropBox = $this->cropCalculator->calculate($poi, $target, $originalDimensions);
            $chain = $chain->with(new Crop($cropBox))->with(Thumbnail::inset($target));
        } else {
            $chain = $chain->with(Thumbnail::outbound(new Dimensions($width, $height)));
        }

        return new VariantSpec($chain, $this->resolveFormat($merged), $this->resolveQuality($merged));
    }

    /**
     * Recursively merges config layers like array_replace_recursive(), except that
     * indexed (list) arrays are replaced wholesale rather than merged element-by-index.
     * array_replace_recursive() would merge e.g. filters.resize.size: [800, 600] with a
     * later layer's [400] into [400, 600] — silently keeping the base height instead of
     * failing on the now-incomplete pair. Only genuinely associative arrays get merged key
     * by key; a list always simply replaces whatever came before it.
     *
     * @param array<array-key, mixed> ...$layers
     *
     * @return array<array-key, mixed>
     */
    private static function mergeLayers(array ...$layers): array
    {
        $merged = [];
        foreach ($layers as $layer) {
            foreach ($layer as $key => $value) {
                $existing = $merged[$key] ?? null;
                if (is_array($value) && is_array($existing) && !array_is_list($value) && !array_is_list($existing)) {
                    $merged[$key] = self::mergeLayers($existing, $value);
                } else {
                    $merged[$key] = $value;
                }
            }
        }

        return $merged;
    }

    /**
     * @param array<array-key, mixed> $merged
     */
    private function parseFilters(array $merged): FilterChain
    {
        $rawFilters = $merged['filters'] ?? [];
        if (!is_array($rawFilters)) {
            throw new InvalidFilterDefinition('The "filters" config key must be an array.');
        }

        $chain = FilterChain::empty();
        foreach ($rawFilters as $name => $options) {
            if (!is_string($name) || !is_array($options)) {
                throw new InvalidFilterDefinition('Malformed filter definition in merged config.');
            }

            $chain = $chain->with($this->filterFactory->create($name, $options));
        }

        return $chain;
    }

    /**
     * @param array<array-key, mixed> $merged
     */
    private function resolveFormat(array $merged): OutputFormat
    {
        if (!isset($merged['format'])) {
            return $this->defaultFormat;
        }

        if (!is_string($merged['format'])) {
            throw new InvalidFilterDefinition('The "format" config key must be a string.');
        }

        return OutputFormat::from($merged['format']);
    }

    /**
     * @param array<array-key, mixed> $merged
     */
    private function resolveQuality(array $merged): Quality
    {
        if (!isset($merged['quality'])) {
            return $this->defaultQuality;
        }

        if (!is_numeric($merged['quality'])) {
            throw new InvalidFilterDefinition('The "quality" config key must be numeric.');
        }

        return new Quality((int) $merged['quality']);
    }
}
