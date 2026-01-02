<?php

declare(strict_types=1);

namespace Tito10047\ProgressiveImageBundle\Service;

use Liip\ImagineBundle\Exception\Imagine\Filter\NonExistingFilterException;
use Liip\ImagineBundle\Imagine\Filter\FilterConfiguration;

class LiipImagineRuntimeConfigGenerator
{
	public function __construct(
		private readonly FilterConfiguration $filterConfiguration,
	) {
	}

	/**
	 * @return array{filterName: string, config: array<string, mixed>}
	 */
	public function generate(int $width, int $height, ?string $filter = null, ?string $pointInterest = null, ?int $origWidth = null, ?int $origHeight = null): array
	{
		$filterName = $filter ? sprintf('%s_%dx%d', $filter, $width, $height) : sprintf('%dx%d', $width, $height);

		if ($pointInterest) {
			$filterName .= '_' . $pointInterest;
		}

		$config = [];
		if ($filter !== null) {
			try {
				$config = $this->filterConfiguration->get($filter);
			} catch (NonExistingFilterException) {
			}
		}

		if (!isset($config['filters'])) {
			$config['filters'] = [];
		}

		if ($pointInterest && $origWidth && $origHeight) {
			[$poiX, $poiY] = explode('x', $pointInterest);
			$config['filters']['crop'] = [
				'start' => $this->calculateCropStart((int)$poiX, (int)$poiY, $width, $height, $origWidth, $origHeight),
				'size' => [$width, $height],
			];
		}

		$config['filters']['thumbnail'] = [
			'size' => [$width, $height],
			'mode' => 'outbound',
		];

		return [
			'filterName' => $filterName,
			'config' => $config,
		];
	}

	/**
	 * @return array{int, int}
	 */
	private function calculateCropStart(int $poiX, int $poiY, int $targetWidth, int $targetHeight, int $origWidth, int $origHeight): array
	{
		// 1. Vypočítame stred orezu na originálnom obrázku podľa percentuálneho bodu záujmu
		$centerX = (int) ($poiX / 100 * $origWidth);
		$centerY = (int) ($poiY / 100 * $origHeight);

		// 2. Vypočítame začiatok orezu tak, aby stred bol na bode záujmu
		$startX = $centerX - (int) ($targetWidth / 2);
		$startY = $centerY - (int) ($targetHeight / 2);

		// 3. Zabezpečíme, aby orez nevychádzal mimo originálny obrázok
		$startX = max(0, min($startX, $origWidth - $targetWidth));
		$startY = max(0, min($startY, $origHeight - $targetHeight));

		return [$startX, $startY];
	}
}
