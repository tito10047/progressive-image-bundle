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

namespace Tito10047\ProgressiveImageBundle\Variant\Domain\Filter;

/**
 * @implements \IteratorAggregate<int, Filter>
 */
final readonly class FilterChain implements \IteratorAggregate, \Countable
{
    /** @var list<Filter> */
    private array $filters;

    private function __construct(Filter ...$filters)
    {
        $this->filters = array_values($filters);
    }

    public static function empty(): self
    {
        return new self();
    }

    public static function of(Filter ...$filters): self
    {
        return new self(...$filters);
    }

    public function with(Filter $filter): self
    {
        return new self(...$this->filters, ...[$filter]);
    }

    public function without(string ...$filterClasses): self
    {
        $remaining = array_filter(
            $this->filters,
            static fn (Filter $filter): bool => !\in_array($filter::class, $filterClasses, true)
        );

        return new self(...array_values($remaining));
    }

    public function isEmpty(): bool
    {
        return [] === $this->filters;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function canonical(): array
    {
        return array_map(static fn (Filter $filter): array => $filter->canonical(), $this->filters);
    }

    public function count(): int
    {
        return \count($this->filters);
    }

    /**
     * @return \ArrayIterator<int, Filter>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->filters);
    }
}
