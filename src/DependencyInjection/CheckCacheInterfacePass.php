<?php

namespace Tito10047\ProgressiveImageBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class CheckCacheInterfacePass implements CompilerPassInterface
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

        // Ak ide o cache pool definovaný cez FrameworkBundle, Symfony ho v build čase
        // premení na definíciu, ktorej class je napr. Symfony\Component\Cache\Adapter\ArrayAdapter.
        // Ak má povolené tagy, Symfony ho obalí do TagAwareAdapter.
        
        $class = $container->getParameterBag()->resolveValue($definition->getClass());
        
        // Ak je trieda prázdna, môže to byť factory. V tom prípade skúsime pozrieť na factory
        if (!$class && $definition->getFactory()) {
            // Tu je ťažké určiť návratový typ factory v compile čase bez vykonania kódu
        }

        if ($class && !is_subclass_of($class, TagAwareCacheInterface::class) && $class !== TagAwareCacheInterface::class) {
            throw new \LogicException(sprintf(
                'Služba cache "%1$s" (trieda: %2$s) musí implementovať TagAwareCacheInterface, aby mohla byť použitá v ProgressiveImageBundle. ' .
                'Skontrolujte, či máte v konfigurácii framework.cache povolené "tags: true" pre tento pool a následne ho nastavte v konfigurácii bundle: ' .
                'progressive_image: { image_cache_service: "%1$s" }. ' .
                'Príklad konfigurácie poolu: framework: { cache: { pools: { %1$s: { adapter: cache.adapter.redis_tag_aware, tags: true } } } }',
                $cacheServiceId,
                $class
            ));
        }

        // Špeciálna kontrola pre Symfony cache pooly, ktoré nemajú nastavený class hneď,
        // ale vieme o nich zistiť, či sú taggable.
        if (!$class || $class === 'Symfony\Component\Cache\Adapter\ArrayAdapter' || $class === 'Symfony\Component\Cache\Adapter\FilesystemAdapter') {
             if (!$definition->hasTag('cache.taggable')) {
                 // Ak nemá tag cache.taggable a zároveň trieda nie je TagAware, vyhodíme chybu.
                 // Symfony TagAwareAdapter má triedu nastavenú na TagAwareAdapter.
                 throw new \LogicException(sprintf(
                     'Služba cache "%1$s" nie je "tag aware". Skontrolujte, či máte v konfigurácii framework.cache povolené "tags: true" pre tento pool ' .
                     'a následne ho nastavte v konfigurácii bundle: progressive_image: { image_cache_service: "%1$s" }. ' .
                     'Príklad konfigurácie poolu: framework: { cache: { pools: { %1$s: { adapter: cache.adapter.redis_tag_aware, tags: true } } } }',
                     $cacheServiceId
                 ));
             }
        }
    }
}
