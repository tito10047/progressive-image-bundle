<?php

namespace Tito10047\ProgressiveImageBundle\Decorators;

use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Tito10047\ProgressiveImageBundle\Resolver\PathResolverInterface;

class LiipImagineDecorator implements PathResolverInterface {

	public function __construct(
		private readonly CacheManager $cache
	) { }

	public function resolve(string $path, array $context): string {
		$filter = $context['filter'] ?? throw new \InvalidArgumentException('filter is required');
		$config = $context['config'] ?? [];
		$resolver = $context['resolver'] ?? null;
		$referenceType = $context['referenceType'] ?? UrlGeneratorInterface::ABSOLUTE_URL;
		return $this->cache->getBrowserPath($path, $filter, $config, $resolver, $referenceType);
	}
}