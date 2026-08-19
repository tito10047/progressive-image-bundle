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
    /**
     * @param string[] $modifiers
     */
    public function __construct(
        public string $breakpoint,
        public int $columns,
        public ?string $ratio,
        public ?int $width = null,
        public ?int $height = null,
        public ?string $widthPercent = null,
        public array $modifiers = [],
    ) {
    }

    /**
     * Helper method to create from a string.
     */
    public static function fromSegment(string $segment, ?string $ratio): self
    {
        $originalSegment = $segment;
        $modifiers = [];
        if (str_contains($segment, '|')) {
            $parts = explode('|', $segment);
            $segment = array_shift($parts);
            $modifiers = $parts;
        }

        $breakpoint = 'default';
        if (preg_match('/^([a-z0-9_]+):(.*)$/i', $segment, $matches)) {
            $breakpoint = $matches[1];
            $segment = $matches[2];
        }

        $segmentRatio = $ratio;
        if (str_contains($segment, '@')) {
            $atPos = strrpos($segment, '@');
            $segmentRatio = substr($segment, $atPos + 1);
            $segment = substr($segment, 0, $atPos);
            if (str_starts_with($segmentRatio, '[') && str_ends_with($segmentRatio, ']')) {
                $segmentRatio = substr($segmentRatio, 1, -1);
            }
        }

        $width = null;
        $height = null;
        $widthPercent = null;
        $columns = 0;

        if (str_starts_with($segment, '[') && str_ends_with($segment, ']')) {
            $dimensions = substr($segment, 1, -1);
            if (str_contains($dimensions, 'x')) {
                [$widthStr, $heightStr] = explode('x', $dimensions, 2);
                if (!is_numeric($widthStr) || !is_numeric($heightStr)) {
                    throw new \InvalidArgumentException(sprintf('Invalid breakpoint assignment format: "%s"', $originalSegment));
                }
                $width = (int) $widthStr;
                $height = (int) $heightStr;
            } elseif (str_ends_with($dimensions, '%')) {
                $widthPercent = $dimensions;
            } elseif ('' !== $dimensions) {
                $width = (int) $dimensions;
            }
        } elseif (is_numeric($segment)) {
            $columns = (int) $segment;
        } else {
            throw new \InvalidArgumentException(sprintf('Invalid breakpoint assignment format: "%s"', $originalSegment));
        }

        // $width is always set alongside $height (both come from the same "[WxH]"
        // branch above), so checking $height here already implies $width is set too.
        if (null !== $height && null === $segmentRatio) {
            $segmentRatio = $width.'x'.$height;
        }

        return new self(
            $breakpoint,
            $columns,
            $segmentRatio,
            $width,
            $height,
            $widthPercent,
            $modifiers
        );
    }

    /**
     * @return array<BreakpointAssignment>
     */
    public static function parseSegments(string $segments, ?string $ratio): array
    {
        $parts = preg_split('/\s+/', trim($segments), -1, PREG_SPLIT_NO_EMPTY);

        return array_map(fn ($segment) => self::fromSegment($segment, $ratio), $parts);
    }
}
