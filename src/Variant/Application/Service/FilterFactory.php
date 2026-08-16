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
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\AutoRotate;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\Background;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\Crop;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\Filter;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\Grayscale;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\Negative;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\Paste;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\RelativeResize;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\Resize;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\Rotate;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\Thumbnail;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\Watermark;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\WatermarkPosition;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\CropBox;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\Dimensions;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\SourcePath;

/**
 * Translates the raw, YAML-shaped filter config (name => options, as authored in
 * filter_sets / imageConfigs / Twig context) into typed Filter value objects.
 * Unknown filter names or malformed options fail loudly (InvalidFilterDefinition)
 * instead of being silently ignored, as the legacy generator did.
 */
final readonly class FilterFactory
{
    /**
     * @param array<array-key, mixed> $options
     */
    public function create(string $name, array $options): Filter
    {
        return match ($name) {
            'thumbnail' => $this->thumbnail($options),
            'crop' => $this->crop($options),
            'resize' => new Resize($this->dimensions($options, 'size', 'resize')),
            'rotate' => new Rotate($this->int($options, 'angle', 'rotate')),
            'background' => new Background($this->string($options, 'color', 'background')),
            'watermark' => $this->watermark($options),
            'grayscale' => new Grayscale(),
            'negative' => new Negative(),
            'auto_rotate' => new AutoRotate(),
            'paste' => $this->paste($options),
            'relative_resize' => $this->relativeResize($options),
            default => throw new InvalidFilterDefinition(sprintf('Unknown filter "%s".', $name)),
        };
    }

    /**
     * @param array<array-key, mixed> $options
     */
    private function thumbnail(array $options): Thumbnail
    {
        $size = $this->dimensions($options, 'size', 'thumbnail');
        $mode = $options['mode'] ?? 'outbound';

        return match ($mode) {
            'inset' => Thumbnail::inset($size),
            'outbound' => Thumbnail::outbound($size),
            default => throw new InvalidFilterDefinition(sprintf('Unknown thumbnail mode "%s".', $this->stringify($mode))),
        };
    }

    /**
     * @param array<array-key, mixed> $options
     */
    private function crop(array $options): Crop
    {
        $size = $this->dimensions($options, 'size', 'crop');
        $start = $options['start'] ?? [0, 0];

        if (!is_array($start) || 2 !== count($start)) {
            throw new InvalidFilterDefinition('Filter "crop" requires a [x, y] "start" option.');
        }

        [$x, $y] = $this->pair($start, 'x', 'y');

        if (!is_numeric($x) || !is_numeric($y)) {
            throw new InvalidFilterDefinition('Filter "crop" option "start" must contain numeric x and y.');
        }

        return new Crop(new CropBox((int) $x, (int) $y, $size));
    }

    /**
     * @param array<array-key, mixed> $options
     */
    private function watermark(array $options): Watermark
    {
        $image = $this->string($options, 'image', 'watermark');
        $position = $options['position'] ?? WatermarkPosition::Center->value;

        if (!is_string($position) || null === WatermarkPosition::tryFrom($position)) {
            throw new InvalidFilterDefinition(sprintf('Unknown watermark position "%s".', $this->stringify($position)));
        }

        $opacity = $options['opacity'] ?? 100;
        if (!is_numeric($opacity)) {
            throw new InvalidFilterDefinition('Filter "watermark" requires a numeric "opacity" option.');
        }

        return new Watermark(new SourcePath($image), WatermarkPosition::from($position), (int) $opacity);
    }

    /**
     * @param array<array-key, mixed> $options
     */
    private function paste(array $options): Paste
    {
        $image = $this->string($options, 'image', 'paste');
        $x = $options['x'] ?? 0;
        $y = $options['y'] ?? 0;

        if (!is_numeric($x) || !is_numeric($y)) {
            throw new InvalidFilterDefinition('Filter "paste" requires numeric "x"/"y" options.');
        }

        return new Paste(new SourcePath($image), (int) $x, (int) $y);
    }

    /**
     * @param array<array-key, mixed> $options
     */
    private function relativeResize(array $options): RelativeResize
    {
        $widthPercent = $options['width_percent'] ?? null;
        $heightPercent = $options['height_percent'] ?? null;

        if (null !== $widthPercent && !is_numeric($widthPercent)) {
            throw new InvalidFilterDefinition('Filter "relative_resize" option "width_percent" must be numeric.');
        }
        if (null !== $heightPercent && !is_numeric($heightPercent)) {
            throw new InvalidFilterDefinition('Filter "relative_resize" option "height_percent" must be numeric.');
        }

        return new RelativeResize(
            null !== $widthPercent ? (float) $widthPercent : null,
            null !== $heightPercent ? (float) $heightPercent : null,
        );
    }

    /**
     * @param array<array-key, mixed> $options
     */
    private function dimensions(array $options, string $key, string $filterName): Dimensions
    {
        $value = $options[$key] ?? null;

        if (!is_array($value) || 2 !== count($value)) {
            throw new InvalidFilterDefinition(sprintf('Filter "%s" requires a [width, height] "%s" option.', $filterName, $key));
        }

        [$width, $height] = $this->pair($value, 'width', 'height');

        if (!is_numeric($width) || !is_numeric($height)) {
            throw new InvalidFilterDefinition(sprintf('Filter "%s" option "%s" must contain numeric width and height.', $filterName, $key));
        }

        return new Dimensions((int) $width, (int) $height);
    }

    /**
     * Reads a two-element option either positionally (a plain [a, b] list, the common YAML
     * sequence form) or by explicit key name (an associative array) — never by raw storage
     * order, so e.g. {y: 20, x: 10} isn't silently swapped into (x=20, y=10).
     *
     * @param array<array-key, mixed> $value
     *
     * @return array{0: mixed, 1: mixed}
     */
    private function pair(array $value, string $firstKey, string $secondKey): array
    {
        if (array_is_list($value)) {
            return [$value[0], $value[1]];
        }

        return [$value[$firstKey] ?? null, $value[$secondKey] ?? null];
    }

    /**
     * @param array<array-key, mixed> $options
     */
    private function string(array $options, string $key, string $filterName): string
    {
        $value = $options[$key] ?? null;

        if (!is_string($value) || '' === $value) {
            throw new InvalidFilterDefinition(sprintf('Filter "%s" requires a non-empty string "%s" option.', $filterName, $key));
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $options
     */
    private function int(array $options, string $key, string $filterName): int
    {
        $value = $options[$key] ?? null;

        if (!is_numeric($value)) {
            throw new InvalidFilterDefinition(sprintf('Filter "%s" requires a numeric "%s" option.', $filterName, $key));
        }

        return (int) $value;
    }

    private function stringify(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : get_debug_type($value);
    }
}
