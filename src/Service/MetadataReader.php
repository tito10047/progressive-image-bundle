<?php

namespace Tito10047\ProgressiveImageBundle\Service;

use Psr\Cache\InvalidArgumentException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Tito10047\ProgressiveImageBundle\Analyzer\ImageAnalyzerInterface;
use Tito10047\ProgressiveImageBundle\DTO\ImageMetadata;
use Tito10047\ProgressiveImageBundle\Event\ImageNotFoundEvent;
use Tito10047\ProgressiveImageBundle\Exception\PathResolutionException;
use Tito10047\ProgressiveImageBundle\Loader\LoaderInterface;
use Tito10047\ProgressiveImageBundle\Resolver\PathResolverInterface;

class MetadataReader {

	public function __construct(
		private readonly EventDispatcherInterface $dispatcher,
		private readonly CacheInterface           $cache,
		private readonly ImageAnalyzerInterface   $analyzer,
		private readonly LoaderInterface          $loader,
		private readonly PathResolverInterface    $pathResolver,
		private readonly ?int                     $ttl,
		private readonly ?string                  $fallbackPath,
	) {
	}

	/**
	 * @throws InvalidArgumentException
	 * @throws PathResolutionException
	 */
	public function getMetadata(string $src):ImageMetadata {
		return $this->cache->get(md5($src), function (ItemInterface $item) use ($src) {
			if ($this->ttl){
				$item->expiresAfter($this->ttl);
			}
			try {
				$path = $this->pathResolver->resolve($src, []);
			}catch (PathResolutionException){
				try {
					$path = $this->pathResolver->resolve($this->fallbackPath, []);
				}catch (PathResolutionException $e){
					$this->dispatcher->dispatch(
						new ImageNotFoundEvent($src, get_class($this->loader)),
						ImageNotFoundEvent::NAME
					);
					throw $e;
				}
			}
			return $this->analyzer->analyze($this->loader, $path);
		});
	}
}