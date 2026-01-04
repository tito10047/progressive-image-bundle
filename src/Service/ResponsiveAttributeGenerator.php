<?php

/*
 * This file is part of the Progressive Image Bundle.
 *
 * (c) Jozef Môstka <https://github.com/tito10047/progressive-image-bundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tito10047\ProgressiveImageBundle\Service;

use Tito10047\ProgressiveImageBundle\DTO\BreakpointAssignment;
use Tito10047\ProgressiveImageBundle\UrlGenerator\ResponsiveImageUrlGeneratorInterface;

final class ResponsiveAttributeGenerator
{
    /**
     * @param array{
     *      layouts: array<string, array{
     *      min_viewport: int,
     *      max_container: int|null
     *      }>,
     *      columns: int
     *      } $gridConfig
     * @param array<string, string> $ratioConfig
     */
    public function __construct(
        private array $gridConfig,
        private array $ratioConfig,
        private readonly PreloadCollector $preloadCollector,
        private ResponsiveImageUrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * @param BreakpointAssignment[] $assignments
     *
	 * @return array{sizes: string, srcset: string, variables: array<string, string>}
     */
    public function generate(string $path, array $assignments, int $originalWidth, bool $preload, ?string $pointInterest = null): array
    {
        $assignments = $this->sortAssignments($assignments);

        $sizesParts = [];
        $srcsetParts = [];
		$variables = [];
        $processedWidths = [];

        foreach ($assignments as $assignment) {
            $layout = $this->gridConfig['layouts'][$assignment->breakpoint] ?? null;
			if (!$layout && 'default' === $assignment->breakpoint) {
				foreach ($this->gridConfig['layouts'] as $l) {
					if (($l['min_viewport'] ?? null) === 0) {
						$layout = $l;
						break;
					}
				}
			}

            if (!$layout) {
				throw new \InvalidArgumentException(sprintf('Breakpoint "%s" is not defined in the grid configuration.', $assignment->breakpoint));
            }

            [$pixelWidth, $sizeValue] = $this->calculateDimensions($assignment, $layout);

            $size = $this->formatSizePart($layout['min_viewport'], $sizeValue);
            $sizesParts[] = $size;

            $url = $this->generateUrl($path, $assignment, (int) round($pixelWidth), $originalWidth, $processedWidths, $pointInterest);

            if ($url) {
                $actualPixelWidth = (int) round($pixelWidth);
                if ($preload) {
                    $this->preloadCollector->add($url, 'image', 'high', "{$actualPixelWidth}w", $size);
                }

                $srcsetParts[] = $url." {$actualPixelWidth}w";
            }

			$ratio                              = $this->resolveRatio($assignment);
			$suffix                             = 0 === $layout['min_viewport'] ? '' : '-' . $assignment->breakpoint;
			$variables['--img-width' . $suffix] = $sizeValue;
			if ($ratio) {
				$variables['--img-aspect' . $suffix] = (string) $ratio;
			}
        }

        return [
            'sizes' => implode(', ', $sizesParts),
            'srcset' => implode(', ', $srcsetParts),
			'variables' => $variables,
        ];
    }

    private function formatSizePart(int $minViewport, string $sizeValue): string
    {
        return $minViewport > 0
            ? "(min-width: {$minViewport}px) {$sizeValue}"
            : $sizeValue;
    }

    /**
     * @param BreakpointAssignment[] $assignments
     *
     * @return BreakpointAssignment[]
     */
    private function sortAssignments(array $assignments): array
    {
        usort($assignments, fn ($a, $b) => ($this->gridConfig['layouts'][$b->breakpoint]['min_viewport'] ?? 0) <=>
            ($this->gridConfig['layouts'][$a->breakpoint]['min_viewport'] ?? 0)
        );

        return $assignments;
    }

    /**
     * @param array{min_viewport: int, max_container: int|null} $layout
     *
     * @return array{0: float, 1: string}
     */
    private function calculateDimensions(BreakpointAssignment $assignment, array $layout): array
    {
		if (null !== $assignment->width) {
			$pixelWidth = (float) $assignment->width;
			$sizeValue  = $assignment->width . 'px';

			return [$pixelWidth, $sizeValue];
		}

        $totalCols = $this->gridConfig['columns'];
        $maxContainer = $layout['max_container'];

        if ($maxContainer) {
			// Fixed container (e.g. 1320px) -> width in px
            $pixelWidth = ($assignment->columns / $totalCols) * $maxContainer;
            $sizeValue = round($pixelWidth).'px';
        } else {
			// Fluid (null) -> width in vw
            $vwWidth = ($assignment->columns / $totalCols) * 100;
            $sizeValue = round($vwWidth).'vw';
			// For URL calculation we estimate px width from some reasonable max-width (e.g. 1920)
            $pixelWidth = ($vwWidth / 100) * 1920;
        }

        return [$pixelWidth, $sizeValue];
    }

    /**
     * @param array<int, bool> $processedWidths
     */
    private function generateUrl(
        string $path,
        BreakpointAssignment $assignment,
        int $basePixelWidth,
        int $originalWidth,
        array &$processedWidths,
        ?string $pointInterest = null,
    ): ?string {
        $ratio = $this->resolveRatio($assignment);

		if ($basePixelWidth > $originalWidth) {
			$basePixelWidth = $originalWidth;
		}

		if (isset($processedWidths[$basePixelWidth])) {
            return null;
        }

        $targetH = $ratio ? (int) round($basePixelWidth / $ratio) : null;
        $url = $this->urlGenerator->generateUrl($path, $basePixelWidth, $targetH, $pointInterest);

        $processedWidths[$basePixelWidth] = true;

        return $url;
    }

    private function resolveRatio(BreakpointAssignment $assignment): ?float
    {
        $ratioString = $assignment->ratio ?? null;
        if (!$ratioString) {
            return null;
        }

		// If it's a key in ratioConfig, use that
        if (isset($this->ratioConfig[$ratioString])) {
			$ratioString = $this->ratioConfig[$ratioString];
		}

		if (is_numeric($ratioString)) {
			return (float) $ratioString;
        }

		// Otherwise try to parse format "16/9" or "3-4"
        if (preg_match('/^(\d+)[\/-](\d+)$/', $ratioString, $matches)) {
			return (float) $matches[1] / (float) $matches[2];
		}

		// Or format "400x500"
		if (preg_match('/^(\d+)x(\d+)$/', $ratioString, $matches)) {
            return (float) $matches[1] / (float) $matches[2];
        }

		throw new \InvalidArgumentException(sprintf('Invalid ratio format or missing ratio configuration for: "%s"', $ratioString));
    }
}
