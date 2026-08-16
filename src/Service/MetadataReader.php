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

final class MetadataReader implements MetadataReaderInterface
{
    public function __construct(
        private readonly EventDispatcherInterface $dispatcher,
        private readonly CacheInterface $cache,
        private readonly ImageAnalyzerInterface $analyzer,
        private readonly LoaderInterface $loader,
        private readonly PathResolverInterface $pathResolver,
        private readonly ?int $ttl,
        private readonly ?string $fallbackPath,
    ) {
    }

    /**
     * @throws InvalidArgumentException
     * @throws PathResolutionException
     */
    public function getMetadata(string $src): ImageMetadata
    {
        $result = $this->cache->get('pgi_meta_'.md5($src), function (ItemInterface $item) use ($src) {
            if ($this->ttl) {
                $item->expiresAfter($this->ttl);
            }

            try {
                $path = $this->pathResolver->resolve($src);

                return $this->analyzer->analyze($this->loader, $path);
            } catch (PathResolutionException) {
                $this->dispatchEvent($src);

                if (!$this->fallbackPath) {
                    // Cache the negative result too (same key, same TTL): without this, a
                    // permanently-broken $src would re-run resolve() and re-dispatch
                    // ImageNotFoundEvent on every single request forever, since Symfony's
                    // cache->get() never stores anything when its callback throws.
                    return null;
                }

                try {
                    return $this->getFallbackMetadata();
                } catch (PathResolutionException $fallbackException) {
                    $this->dispatchEvent($this->fallbackPath);

                    throw $fallbackException;
                }
            }
        });

        if (null === $result) {
            throw new PathResolutionException(\sprintf('Source image not resolvable "%s"', $src));
        }

        return $result;
    }

    /**
     * @throws PathResolutionException
     */
    private function getFallbackMetadata(): ImageMetadata
    {
        // Keyed by the fallback path itself (not by whichever original $src fell back to
        // it), so every broken $src shares this single cache entry instead of each one
        // separately recomputing and storing an identical copy of the fallback image's
        // (potentially expensive, e.g. blurhash) metadata.
        return $this->cache->get('pgi_meta_'.md5($this->fallbackPath), function (ItemInterface $item) {
            if ($this->ttl) {
                $item->expiresAfter($this->ttl);
            }

            $path = $this->pathResolver->resolve($this->fallbackPath);

            return $this->analyzer->analyze($this->loader, $path);
        });
    }

    public function dispatchEvent(string $src): void
    {
        $this->dispatcher->dispatch(
            new ImageNotFoundEvent($src, get_class($this->loader)),
            ImageNotFoundEvent::NAME
        );
    }
}
