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
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Tito10047\ProgressiveImageBundle\Service\LiipImagineRuntimeConfigGeneratorInterface;
use Liip\ImagineBundle\Service\FormatNegotiator;

final class LiipImagineResponsiveImageUrlGenerator implements ResponsiveImageUrlGeneratorInterface
{
    public function __construct(
        private readonly CacheManager $cacheManager,
        private readonly UrlGeneratorInterface $router,
        private readonly UriSigner $uriSigner,
        private readonly LiipImagineRuntimeConfigGeneratorInterface $runtimeConfigGenerator,
        private readonly FilterConfiguration $filterConfiguration,
        private readonly RequestStack $requestStack,
        private readonly ?TagAwareCacheInterface $cache,
        private readonly ?FormatNegotiator $formatNegotiator = null,
        private readonly array $alternativeFormats = [],
    ) {
    }

    public function generateUrl(string $path, int $targetW, ?int $targetH = null, ?string $pointInterest = null, array $context = []): string
    {
        $targetH = $targetH ?? $targetW;
        $filter = $context['filter'] ?? null;
        $result = $this->runtimeConfigGenerator->generate($targetW, $targetH, $filter, $pointInterest, null, null, $context);
        $filterName = $result['filterName'];
        $config = $result['config'];

        // Register runtime filter so LiipImagine can find it
        try {
            $this->filterConfiguration->get($filterName);
        } catch (NonExistingFilterException) {
            $this->filterConfiguration->set($filterName, $config);
        }

        // Try to return already stored alternative format matching current request preferences
        $request = $this->requestStack->getCurrentRequest();
        if (null !== $request && null !== $this->formatNegotiator) {
            $supported = $this->formatNegotiator->negotiate($request, $this->alternativeFormats);
            foreach ($supported as $format) {
                $altPath = $path.'.'.$format;
                if ($this->cacheManager->isStored($altPath, $filterName)) {
                    return $this->cacheManager->resolve($altPath, $filterName);
                }
            }
        }

        if ($this->cacheManager->isStored($path, $filterName)) {
            return $this->cacheManager->resolve($path, $filterName);
        }

        $this->cache?->invalidateTags(['pgi_tag_'.md5($path)]);

        $params = [
            'path' => $path,
            'width' => $targetW,
            'height' => $targetH,
            'filter' => $filter,
            'pointInterest' => $pointInterest,
        ];
        $params = array_merge($params, $context);

        $url = $this->router->generate('progressive_image_filter', array_filter($params), UrlGeneratorInterface::ABSOLUTE_URL);

        return $this->uriSigner->sign($url);
    }

}
