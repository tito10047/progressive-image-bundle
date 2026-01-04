<?php

/*
 * This file is part of the Progressive Image Bundle.
 *
 * (c) Jozef Môstka <https://github.com/tito10047/progressive-image-bundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tito10047\ProgressiveImageBundle\DTO;

/**
 * Represents a single instruction, e.g. "lg-4@landscape".
 */
final readonly class BreakpointAssignment
{
    public function __construct(
        public string $breakpoint,
        public int $columns,
        public ?string $ratio,
    ) {
    }

    /**
	 * Helper method to create from a string.
     */
    public static function fromSegment(string $segment, ?string $ratio): self
    {
        if (!preg_match('/^([a-z0-9]+)-([0-9]+)(?:@([a-z0-9-]+))?$/i', $segment, $matches)) {
            throw new \InvalidArgumentException(sprintf('Invalid breakpoint assignment format: "%s"', $segment));
        }

        return new self(
            $matches[1],
            (int) $matches[2],
            $matches[3] ?? $ratio ?? null
        );
    }

    /**
     * @return array<BreakpointAssignment>
     */
    public static function parseSegments(string $segments, ?string $ratio): array
    {
        return array_map(fn ($segment) => self::fromSegment($segment, $ratio), explode(' ', $segments));
    }
}
