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
	public function generate(int $width, int $height, ?string $filter = null): array
	{
		$filterName = $filter ? sprintf('%s_%dx%d', $filter, $width, $height) : sprintf('%dx%d', $width, $height);

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

		$config['filters']['thumbnail'] = [
			'size' => [$width, $height],
			'mode' => 'outbound',
		];

		return [
			'filterName' => $filterName,
			'config' => $config,
		];
	}
}
