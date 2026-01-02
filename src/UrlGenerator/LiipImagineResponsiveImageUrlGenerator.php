<?php

declare(strict_types=1);

namespace Tito10047\ProgressiveImageBundle\UrlGenerator;

use Liip\ImagineBundle\Exception\Imagine\Filter\NonExistingFilterException;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Liip\ImagineBundle\Imagine\Filter\FilterConfiguration;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Tito10047\ProgressiveImageBundle\Service\LiipImagineRuntimeConfigGenerator;

class LiipImagineResponsiveImageUrlGenerator implements ResponsiveImageUrlGeneratorInterface
{
	public function __construct(
		private readonly CacheManager $cacheManager,
		private readonly UrlGeneratorInterface $router,
		private readonly UriSigner $uriSigner,
		private readonly LiipImagineRuntimeConfigGenerator $runtimeConfigGenerator,
		private readonly FilterConfiguration $filterConfiguration,
		private readonly ?string $filter = null,
	) {
	}

	public function generateUrl(string $path, int $targetW, ?int $targetH): string
	{
		$targetH = $targetH ?? $targetW;
		$result = $this->runtimeConfigGenerator->generate($targetW, $targetH, $this->filter);
		$filterName = $result['filterName'];
		$config = $result['config'];

		// Register runtime filter so LiipImagine can find it
		try {
			$this->filterConfiguration->get($filterName);
		} catch (NonExistingFilterException) {
			$this->filterConfiguration->set($filterName, $config);
		}

		if ($this->cacheManager->isStored($path, $filterName)) {
			return $this->cacheManager->getBrowserPath($path, $filterName);
		}

		$url = $this->router->generate('progressive_image_filter', [
			'path' => $path,
			'width' => $targetW,
			'height' => $targetH,
			'filter' => $this->filter,
		], UrlGeneratorInterface::ABSOLUTE_URL);

		return $this->uriSigner->sign($url);
	}
}
