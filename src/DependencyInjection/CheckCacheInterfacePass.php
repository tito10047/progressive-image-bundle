<?php

/*
 * This file is part of the Progressive Image Bundle.
 *
 * (c) Jozef Môstka <https://github.com/tito10047/progressive-image-bundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tito10047\ProgressiveImageBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

final class CheckCacheInterfacePass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if ($container->hasParameter('progressive_image.image_cache_enabled') && !$container->getParameter('progressive_image.image_cache_enabled')) {
            return;
        }

        if (!$container->hasAlias('progressive_image.image_cache_service')) {
            return;
        }

        $cacheServiceId = (string) $container->getAlias('progressive_image.image_cache_service');
        if (!$container->hasDefinition($cacheServiceId)) {
            return;
        }

        $definition = $container->getDefinition($cacheServiceId);

        while ($definition->hasTag('container.service_alias')) {
            $tags = $definition->getTag('container.service_alias');
            $cacheServiceId = $tags[0]['alias'] ?? $cacheServiceId;
            if (!$container->hasDefinition($cacheServiceId)) {
                break;
            }
            $definition = $container->getDefinition($cacheServiceId);
        }

		// If it's a cache pool defined via FrameworkBundle, Symfony turns it at build time
		// into a definition whose class is e.g. Symfony\Component\Cache\Adapter\ArrayAdapter.
		// If it has tags enabled, Symfony wraps it in TagAwareAdapter.

        $class = $container->getParameterBag()->resolveValue($definition->getClass());

		// If class is empty, it might be a factory. In that case we try to look at the factory
        if (!$class && $definition->getFactory()) {
			// Here it's difficult to determine the factory return type at compile time without executing code
        }

        if ($class && !is_subclass_of($class, TagAwareCacheInterface::class) && TagAwareCacheInterface::class !== $class) {
			throw new \LogicException(sprintf('Cache service "%1$s" (class: %2$s) must implement TagAwareCacheInterface to be used in ProgressiveImageBundle. Check if you have "tags: true" enabled for this pool in framework.cache configuration and then set it in bundle configuration: progressive_image: { image_cache_service: "%1$s" }. Example pool configuration: framework: { cache: { pools: { %1$s: { adapter: cache.adapter.redis_tag_aware, tags: true } } } }', $cacheServiceId, $class));
        }

		// Special check for Symfony cache pools that don't have a class set immediately,
		// but we can find out if they are taggable.
        if (!$class || 'Symfony\Component\Cache\Adapter\ArrayAdapter' === $class || 'Symfony\Component\Cache\Adapter\FilesystemAdapter' === $class) {
            if (!$definition->hasTag('cache.taggable')) {
				// If it doesn't have the cache.taggable tag and the class is not TagAware, we throw an error.
				// Symfony TagAwareAdapter has the class set to TagAwareAdapter.
				throw new \LogicException(sprintf('Cache service "%1$s" is not "tag aware". Check if you have "tags: true" enabled for this pool in framework.cache configuration and then set it in bundle configuration: progressive_image: { image_cache_service: "%1$s" }. Example pool configuration: framework: { cache: { pools: { %1$s: { adapter: cache.adapter.redis_tag_aware, tags: true } } } }', $cacheServiceId));
            }
        }
    }
}
