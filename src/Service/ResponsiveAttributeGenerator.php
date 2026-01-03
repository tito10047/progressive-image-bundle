<?php

namespace Tito10047\ProgressiveImageBundle\Service;

use Tito10047\ProgressiveImageBundle\Dto\BreakpointAssignment;
use Tito10047\ProgressiveImageBundle\UrlGenerator\ResponsiveImageUrlGeneratorInterface;

final class ResponsiveAttributeGenerator {

	/**
	 * @param $gridConfig  array{
	 *     layouts: array<string, array{
	 *            min_viewport: int,
	 *            max_container: int|null
	 *       }>,
	 *     columns: int
	 * }
	 * @param $ratioConfig array<string, string>
	 */
	public function __construct(
		private array                                $gridConfig,
		private array                                $ratioConfig,
		private readonly PreloadCollector            $preloadCollector,
		private ResponsiveImageUrlGeneratorInterface $urlGenerator
	) {
	}

	/**
	 * @return array{sizes: string, srcset: string}
	 */
	public function generate(string $path, array $assignments, int $originalWidth, bool $preload, ?string $pointInterest = null): array {
		$assignments = $this->sortAssignments($assignments);

		$sizesParts      = [];
		$srcsetParts     = [];
		$processedWidths = [];

		foreach ($assignments as $assignment) {
			$layout = $this->gridConfig['layouts'][$assignment->breakpoint] ?? null;
			if (!$layout) {
				continue;
			}

			[$pixelWidth, $sizeValue] = $this->calculateDimensions($assignment, $layout);

			$size = $this->formatSizePart($layout['min_viewport'], $sizeValue);
			$sizesParts[]   = $size;

			$url = $this->generateUrl($path, $assignment, (int) round($pixelWidth), $originalWidth, $processedWidths, $pointInterest);

			if ($url) {
				$actualPixelWidth = (int) round($pixelWidth);
				if ($preload) {
					$this->preloadCollector->add($url, 'image', 'high', "{$actualPixelWidth}w", $size);
				}

				$srcsetParts[] = $url . " {$actualPixelWidth}w";
			}
		}

		return [
			'sizes'  => implode(', ', $sizesParts),
			'srcset' => implode(', ', $srcsetParts),
		];
	}

	private function formatSizePart(int $minViewport, string $sizeValue): string {
		return $minViewport > 0
			? "(min-width: {$minViewport}px) {$sizeValue}"
			: $sizeValue;
	}

	/**
	 * @param BreakpointAssignment[] $assignments
	 *
	 * @return BreakpointAssignment[]
	 */
	private function sortAssignments(array $assignments): array {
		usort($assignments, fn($a, $b) => ($this->gridConfig['layouts'][$b->breakpoint]['min_viewport'] ?? 0) <=>
			($this->gridConfig['layouts'][$a->breakpoint]['min_viewport'] ?? 0)
		);

		return $assignments;
	}

	/**
	 * @param array{min_viewport: int, max_container: int|null} $layout
	 *
	 * @return array{0: float, 1: string}
	 */
	private function calculateDimensions(BreakpointAssignment $assignment, array $layout): array {
		$totalCols    = $this->gridConfig['columns'];
		$maxContainer = $layout['max_container'];

		if ($maxContainer) {
			// Fixný kontajner (napr. 1320px) -> šírka v px
			$pixelWidth = ($assignment->columns / $totalCols) * $maxContainer;
			$sizeValue  = round($pixelWidth) . 'px';
		} else {
			// Fluid (null) -> šírka vo vw
			$vwWidth   = ($assignment->columns / $totalCols) * 100;
			$sizeValue = round($vwWidth) . 'vw';
			// Pre výpočet URL odhadneme px šírku z nejakej rozumnej max-šírky (napr. 1920)
			$pixelWidth = ($vwWidth / 100) * 1920;
		}

		return [$pixelWidth, $sizeValue];
	}

	/**
	 * @param array<int, bool> $processedWidths
	 *
	 * @return string[]
	 */
	private function generateUrl(
		string               $path,
		BreakpointAssignment $assignment,
		int                  $basePixelWidth,
		int                  $originalWidth,
		array                &$processedWidths,
		?string              $pointInterest = null
	): ?string {
		$ratio = $this->resolveRatio($assignment);

		if ($basePixelWidth > $originalWidth || isset($processedWidths[$basePixelWidth])) {
			return null;
		}

		$targetH = $ratio ? (int) round($basePixelWidth / $ratio) : null;
		$url     = $this->urlGenerator->generateUrl($path, $basePixelWidth, $targetH, $pointInterest);

		$processedWidths[$basePixelWidth] = true;
		return $url;

	}

	private function resolveRatio(BreakpointAssignment $assignment): ?float {
		$ratioString = $assignment->ratio ?? null;
		if (!$ratioString) {
			return null;
		}

		// Ak je to kľúč v ratioConfig, použijeme ten
		if (isset($this->ratioConfig[$ratioString])) {
			return (float) $this->ratioConfig[$ratioString];
		}

		// Inak skúsime parsovať formát "3/4" alebo "3-4"
		if (preg_match('/^(\d+)[\/-](\d+)$/', $ratioString, $matches)) {
			return (float) $matches[1] / (float) $matches[2];
		}

		return null;
	}
}