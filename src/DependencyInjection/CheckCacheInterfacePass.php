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

use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
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

        // These are "provisional" classes: either the class couldn't be determined at all
        // (e.g. a factory-defined service, whose return type we can't resolve without
        // executing code) or it's one of the raw adapters FrameworkBundle uses internally
        // before wrapping a pool in TagAwareAdapter when "tags: true" is set. Neither case
        // can be checked via is_subclass_of(), so we trust the cache.taggable tag instead.
        // This check MUST run before the strict TagAwareCacheInterface check below: both
        // ArrayAdapter and FilesystemAdapter fail is_subclass_of(TagAwareCacheInterface),
        // so if the strict check ran first it would reject every provisional pool outright,
        // making this fallback unreachable.
        if (!$class || ArrayAdapter::class === $class || FilesystemAdapter::class === $class) {
            if (!$definition->hasTag('cache.taggable')) {
                throw new \LogicException(sprintf('Cache service "%1$s" is not "tag aware". Check if you have "tags: true" enabled for this pool in framework.cache configuration and then set it in bundle configuration: progressive_image: { image_cache_service: "%1$s" }. Example pool configuration: framework: { cache: { pools: { %1$s: { adapter: tags: true } } } }', $cacheServiceId));
            }

            return;
        }

        if (!is_subclass_of($class, TagAwareCacheInterface::class) && TagAwareCacheInterface::class !== $class) {
            throw new \LogicException(sprintf('Cache service "%1$s" (class: %2$s) must implement TagAwareCacheInterface to be used in ProgressiveImageBundle. Check if you have "tags: true" enabled for this pool in framework.cache configuration and then set it in bundle configuration: progressive_image: { image_cache_service: "%1$s" }. Example pool configuration: framework: { cache: { pools: { %1$s: { adapter: tags: true } } } }', $cacheServiceId, $class));
        }
    }
}
