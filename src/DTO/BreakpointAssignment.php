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
 * Represents a single instruction, e.g. "lg:4@landscape".
 */
final readonly class BreakpointAssignment
{
    public function __construct(
        public string $breakpoint,
        public int $columns,
        public ?string $ratio,
		public ?int $width = null,
		public ?int $height = null,
		public ?string $widthPercent = null,
    ) {
    }

    /**
	 * Helper method to create from a string.
     */
    public static function fromSegment(string $segment, ?string $ratio): self
    {
		if (preg_match('/^(?:([a-z0-9]+):)?\[(\d+)?(%)?(?:x(\d+))?\](?:@([a-z0-9\/-]+))?$/i', $segment, $matches)) {
			$widthPercent = ($matches[3] ?? '') === '%' ? $matches[2] . '%' : null;
			$width        = $widthPercent === null && ($matches[2] ?? '') !== '' ? (int) $matches[2] : null;
			$height       = ($matches[4] ?? '') !== '' ? (int) $matches[4] : null;
			$r            = ($matches[5] ?? '') !== '' ? $matches[5] : ($ratio ?? null);
			if (null !== $height && null === $r && null !== $width) {
				$r = $width . 'x' . $height;
			}

			return new self(
				'' !== $matches[1] ? $matches[1] : 'default',
				0,
				$r,
				$width,
				$height,
				$widthPercent
			);
		}

		if (preg_match('/^(?:([a-z0-9]+):)?([0-9]+)(?:@([a-z0-9\/-]+))?$/i', $segment, $matches)) {
			return new self(
				'' !== $matches[1] ? $matches[1] : 'default',
				(int) $matches[2],
				($matches[3] ?? '') !== '' ? $matches[3] : ($ratio ?? null)
			);
		}

		throw new \InvalidArgumentException(sprintf('Invalid breakpoint assignment format: "%s"', $segment));
    }

    /**
     * @return array<BreakpointAssignment>
     */
    public static function parseSegments(string $segments, ?string $ratio): array
    {
        return array_map(fn ($segment) => self::fromSegment($segment, $ratio), explode(' ', $segments));
    }
}
