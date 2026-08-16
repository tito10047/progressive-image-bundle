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

/**
 * Immutable, boot-time-validated snapshot of the "filter_sets" bundle config.
 * A typo in a filter name or a malformed option in the RAW "filter_sets" config breaks the
 * boot (constructor throws). This does NOT guarantee the fully merged runtime config (raw
 * filter set + imageConfigs + per-request Twig context, see VariantSpecFactory::create())
 * is valid — that merge can only happen per-request, since context isn't known at boot —
 * but VariantSpecFactory::parseFilters() still fails loudly (InvalidFilterDefinition) at
 * request time if the merge produces something malformed, it just can't be caught earlier.
 * Raw definitions are kept (not pre-parsed into FilterChain) because VariantSpecFactory
 * still needs to recursively merge them with imageConfigs and per-call context before
 * turning the result into typed filters.
 */
final readonly class FilterSetRegistry
{
    /** @var array<string, array<string, mixed>> */
    private array $rawSets;

    /**
     * @param array<string, array<string, mixed>> $rawFilterSets
     */
    public function __construct(array $rawFilterSets, FilterFactory $filterFactory)
    {
        foreach ($rawFilterSets as $name => $definition) {
            $filters = $definition['filters'] ?? [];
            if (!is_array($filters)) {
                throw new InvalidFilterDefinition(sprintf('Filter set "%s" has a non-array "filters" key.', $name));
            }

            foreach ($filters as $filterName => $options) {
                if (!is_string($filterName) || !is_array($options)) {
                    throw new InvalidFilterDefinition(sprintf('Filter set "%s" has a malformed filter definition.', $name));
                }

                $filterFactory->create($filterName, $options);
            }
        }

        $this->rawSets = $rawFilterSets;
    }

    public function has(string $name): bool
    {
        return isset($this->rawSets[$name]);
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->rawSets);
    }

    /**
     * @return array<string, mixed>
     */
    public function rawFilterSet(string $name): array
    {
        return $this->rawSets[$name] ?? throw new InvalidFilterDefinition(sprintf('Unknown filter set "%s".', $name));
    }
}
