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

namespace Tito10047\ProgressiveImageBundle\UrlGenerator;

use Liip\ImagineBundle\Exception\Imagine\Filter\NonExistingFilterException;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Liip\ImagineBundle\Imagine\Filter\FilterConfiguration;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Tito10047\ProgressiveImageBundle\Service\LiipImagineRuntimeConfigGeneratorInterface;

final class LiipImagineResponsiveImageUrlGenerator implements ResponsiveImageUrlGeneratorInterface
{
    public function __construct(
        private readonly CacheManager $cacheManager,
        private readonly UrlGeneratorInterface $router,
        private readonly UriSigner $uriSigner,
        private readonly LiipImagineRuntimeConfigGeneratorInterface $runtimeConfigGenerator,
        private readonly FilterConfiguration $filterConfiguration,
        private readonly ?TagAwareCacheInterface $cache,
        private readonly ?string $filter = null,
    ) {
    }

	public function generateUrl(string $path, int $targetW, ?int $targetH = null, ?string $pointInterest = null): string
    {
        $targetH = $targetH ?? $targetW;
        $result = $this->runtimeConfigGenerator->generate($targetW, $targetH, $this->filter, $pointInterest);
        $filterName = $result['filterName'];
        $config = $result['config'];

        // Register runtime filter so LiipImagine can find it
        try {
            $this->filterConfiguration->get($filterName);
        } catch (NonExistingFilterException) {
            $this->filterConfiguration->set($filterName, $config);
        }

        if ($this->cacheManager->isStored($path, $filterName)) {
			$browserPath = $this->cacheManager->getBrowserPath($path, $filterName);
			return str_replace('/resolve/', '/', $browserPath);
        }

        $this->cache?->invalidateTags(['pgi_tag_'.md5($path)]);

        $url = $this->router->generate('progressive_image_filter', [
            'path' => $path,
            'width' => $targetW,
            'height' => $targetH,
            'filter' => $this->filter,
            'pointInterest' => $pointInterest,
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        return $this->uriSigner->sign($url);
    }
}
