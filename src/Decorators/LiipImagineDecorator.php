<?php

namespace Tito10047\ProgressiveImageBundle\Decorators;

use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Liip\ImagineBundle\Imagine\Filter\FilterConfiguration;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Tito10047\ProgressiveImageBundle\Resolver\PathResolverInterface;

class LiipImagineDecorator implements PathDecoratorInterface {

	public function __construct(
		private readonly CacheManager $cache,
		private readonly FilterConfiguration $configuration
	) {
	}


	public function decorate(string $path, array $context = []): string {
		$filter = $context['filter'] ?? null;
		if (!$filter) {
			return $path;
		}
		if ($this->cache->isStored($path, $filter)) {
			return $this->cache->resolve($path, $filter);
		}
		$config = $context['config'] ?? [];
		$resolver = $context['resolver'] ?? null;
		$referenceType = $context['referenceType'] ?? UrlGeneratorInterface::ABSOLUTE_URL;
		return $this->cache->getBrowserPath($path, $filter, $config, $resolver, $referenceType);
	}


	public function getSize(string $path, array $context = []): ?array {
		$filter = $context['filter'] ?? null;
		if (!$filter) {
			return null;
		}
		$config = $this->configuration->get($filter);
		$size = $config["filters"]["thumbnail"]["size"]??null;
		if (!$size) return null;
		return ["width"=>$size[0],"height"=>$size[1]];
	}
}