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

use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;
use Liip\ImagineBundle\LiipImagineBundle;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;
use Symfony\Component\Lock\Store\StoreFactory;
use Symfony\Component\Messenger\MessageBusInterface;
use Tito10047\ProgressiveImageBundle\Command\GenerateCustomCssCommand;
use Tito10047\ProgressiveImageBundle\Controller\LiipImagineController;
use Tito10047\ProgressiveImageBundle\Event\TransparentImageCacheSubscriber;
use Tito10047\ProgressiveImageBundle\Modifier\BaseFilterModifier;
use Tito10047\ProgressiveImageBundle\Modifier\FilterModifierInterface;
use Tito10047\ProgressiveImageBundle\Modifier\ModifierInterface;
use Tito10047\ProgressiveImageBundle\Modifier\ModifierProvider;
use Tito10047\ProgressiveImageBundle\Resolver\AssetMapperResolver;
use Tito10047\ProgressiveImageBundle\Resolver\ChainResolver;
use Tito10047\ProgressiveImageBundle\Resolver\FileSystemResolver;
use Tito10047\ProgressiveImageBundle\Service\LiipImagineRuntimeConfigGenerator;
use Tito10047\ProgressiveImageBundle\Service\LiipImagineRuntimeConfigGeneratorInterface;
use Tito10047\ProgressiveImageBundle\Service\MetadataReader;
use Tito10047\ProgressiveImageBundle\Service\PreloadCollector;
use Tito10047\ProgressiveImageBundle\Service\ResponsiveAttributeGenerator;
use Tito10047\ProgressiveImageBundle\Twig\Components\Image;
use Tito10047\ProgressiveImageBundle\Twig\TransparentCacheExtension;
use Tito10047\ProgressiveImageBundle\UrlGenerator\LiipImagineResponsiveImageUrlGenerator;
use Tito10047\ProgressiveImageBundle\UrlGenerator\ResponsiveImageUrlGeneratorInterface;
use Tito10047\ProgressiveImageBundle\Variant\Application\Command\GenerateVariant;
use Tito10047\ProgressiveImageBundle\Variant\Application\Handler\GenerateVariantHandler;
use Tito10047\ProgressiveImageBundle\Variant\Application\Handler\ResolveVariantUrlHandler;
use Tito10047\ProgressiveImageBundle\Variant\Application\Port\DomainEventBus;
use Tito10047\ProgressiveImageBundle\Variant\Application\Port\GenerationDispatcher;
use Tito10047\ProgressiveImageBundle\Variant\Application\Port\OriginalUrlResolver;
use Tito10047\ProgressiveImageBundle\Variant\Application\Port\PendingUrlBuilder;
use Tito10047\ProgressiveImageBundle\Variant\Application\Port\UrlSigner;
use Tito10047\ProgressiveImageBundle\Variant\Application\Query\PendingFallbackStrategy;
use Tito10047\ProgressiveImageBundle\Variant\Application\Service\FilterFactory;
use Tito10047\ProgressiveImageBundle\Variant\Application\Service\FilterSetRegistry;
use Tito10047\ProgressiveImageBundle\Variant\Application\Service\PendingGenerationTracker;
use Tito10047\ProgressiveImageBundle\Variant\Application\Service\VariantSpecFactory;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\OutputFormat;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\Quality;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Port\Clock;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Port\GenerationLock;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Port\ImageManipulator;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Port\PostProcessor;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Port\SourceReader;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Port\VariantStorage;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Service\AspectCropCalculator;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Service\VariantIdHasher;
use Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Flysystem\FlysystemVariantStorage;
use Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Intervention\InterventionImageManipulator;
use Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Lock\SymfonyGenerationLock;
use Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Messenger\GenerateVariantMessageHandler;
use Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Messenger\MessengerGenerationDispatcher;
use Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Presentation\Controller\ImageVariantController;
use Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Presentation\EventListener\ResponseCacheOverrideListener;
use Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Presentation\UrlGenerator\QueryPendingUrlBuilder;
use Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Presentation\UrlGenerator\VariantResponsiveImageUrlGenerator;
use Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Source\ResolverChainSourceReader;
use Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Symfony\DefaultOriginalUrlResolver;
use Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Symfony\SymfonyDomainEventBus;
use Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Symfony\SymfonyUriSigner;
use Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Symfony\SystemClock;
use Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Sync\SyncGenerationDispatcher;
use Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Terminate\TerminateGenerationDispatcher;

final class ProgressiveImageExtension extends Extension implements PrependExtensionInterface
{
    public function getAlias(): string
    {
        return 'progressive_image';
    }

    public function prepend(ContainerBuilder $builder): void
    {
        $builder->prependExtensionConfig('framework', [
            'asset_mapper' => [
                'paths' => [
                    __DIR__.'/../../assets' => 'tito10047/progressive-image-bundle',
                ],
            ],
        ]);
        $builder->prependExtensionConfig('twig_component', [
            'defaults' => [
                'Tito10047\ProgressiveImageBundle\Twig\Components\\' => [
                    'template_directory' => '@ProgressiveImage/components/',
                    'name_prefix' => 'pgi',
                ],
            ],
        ]);

        $configs = $builder->getExtensionConfig($this->getAlias());
        $configs = $this->processConfiguration(new Configuration(), $configs);

        if (isset($configs['responsive_strategy']['breakpoints'])) {
            $breakpoints = $configs['responsive_strategy']['breakpoints'];
            $liipConfigs = $builder->getExtensionConfig('liip_imagine');

            $newFilterSets = [];
            foreach ($liipConfigs as $liipConfig) {
                if (isset($liipConfig['filter_sets'])) {
                    foreach ($liipConfig['filter_sets'] as $setName => $setConfig) {
                        foreach ($breakpoints as $breakpointName => $width) {
                            $newSetName = $setName.'_'.$breakpointName;
                            if (isset($newFilterSets[$newSetName])) {
                                continue;
                            }
                            $newSetConfig = $setConfig;

                            if (isset($newSetConfig['filters']['thumbnail']['size'])) {
                                [$origWidth, $origHeight] = $newSetConfig['filters']['thumbnail']['size'];
                                if ($origWidth > 0 && $origHeight > 0) {
                                    $ratio = $origHeight / $origWidth;
                                    $newHeight = (int) round($width * $ratio);
                                    $newSetConfig['filters']['thumbnail']['size'] = [$width, $newHeight];
                                } else {
                                    $newSetConfig['filters']['thumbnail']['size'] = [$width, $width];
                                }
                            }

                            $newFilterSets[$newSetName] = $newSetConfig;
                        }
                    }
                }
            }

            if (!empty($newFilterSets)) {
                $builder->prependExtensionConfig('liip_imagine', [
                    'filter_sets' => $newFilterSets,
                ]);
            }
        }
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $configs = $this->processConfiguration(new Configuration(), $configs);

        if (!isset($container->getParameter('kernel.bundles')['TwigBundle'])) {
            throw new \LogicException('The TwigBundle is not registered in your application. Try running "composer require symfony/twig-bundle".');
        }

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__.'/../../config'));
        $loader->load('services.php');

        $this->configureResolvers($configs, $container);
        $this->configureVariantContext($configs, $container);

        $driver = $configs['driver'] ?? 'gd';
        $analyzerId = match ($driver) {
            'imagick' => 'progressive_image.analyzer.imagick',
            'gd' => 'progressive_image.analyzer.gd',
            default => $driver,
        };

        $loaderId = $configs['loader'] ?? 'progressive_image.filesystem.loader';
        $cacheId = $configs['cache'] ?? 'cache.app';
        $imageCacheServiceId = $configs['image_cache_service'] ?? 'cache.app';
        $imageCacheEnabled = $configs['image_cache_enabled'] ?? false;
        $ttl = $configs['ttl'] ?? null;
        $retinaConfig = $configs['retina'] ?? ['enabled' => true, 'multipliers' => [1, 2]];
        $retina = $retinaConfig['enabled'] ?? true;
        $retinaMultipliers = $retinaConfig['multipliers'] ?? [1, 2];

        if (!$imageCacheEnabled) {
            $imageCacheServiceReference = null;
        } else {
            $imageCacheServiceReference = new Reference('progressive_image.image_cache_service');
        }

        $definition = $container->getDefinition(MetadataReader::class);
        $definition->setArgument('$analyzer', new Reference($analyzerId))
            ->setArgument('$loader', new Reference($loaderId))
            ->setArgument('$pathResolver', new Reference('progressive_image.resolver.default'))
            ->setArgument('$cache', new Reference($cacheId))
            ->setArgument('$ttl', $configs['ttl'] ?? null)
            ->setArgument('$fallbackPath', $configs['fallback_image'] ?? null)
        ;
        $container->setParameter('progressive_image.image_cache_enabled', $imageCacheEnabled);
        $container->setParameter('progressive_image.ttl', $ttl);
        $container->setParameter('progressive_image.image_configs', $configs['image_configs'] ?? []);
        $container->setParameter('progressive_image.responsive_strategy.ratios', $configs['responsive_strategy']['ratios'] ?? []);
        $container->setAlias('progressive_image.image_cache_service', $imageCacheServiceId);

        $container->register(TransparentCacheExtension::class)
            ->setArgument('$ttl', new Parameter('progressive_image.ttl'))
            ->setArgument('$cache', $imageCacheServiceReference)
            ->addTag('twig.extension')
        ;
        if (null !== ($configs['variant_store']['storage'] ?? null)) {
            $container->getDefinition(TransparentCacheExtension::class)
                ->setArgument('$tracker', new Reference(PendingGenerationTracker::class));
        }

        $container->register(TransparentImageCacheSubscriber::class)
            ->setArgument('$enabled', new Parameter('progressive_image.image_cache_enabled'))
            ->setArgument('$cache', $imageCacheServiceReference)
            ->setArgument('$ttl', new Parameter('progressive_image.ttl'))
            ->addTag('kernel.event_subscriber')
        ;

        $container->registerForAutoconfiguration(ModifierInterface::class)
            ->addTag('progressive_image.modifier');

        $container->registerForAutoconfiguration(FilterModifierInterface::class)
            ->addTag('pgi.filter_modifier');

        $container->register(ModifierProvider::class)
            ->setArgument('$modifiers', new \Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument('progressive_image.modifier'));

        $container->register(BaseFilterModifier::class)
            ->addTag('progressive_image.modifier', ['priority' => -100]);

        if (class_exists(LiipImagineBundle::class)) {
            $container->register(LiipImagineRuntimeConfigGenerator::class)
                ->setArgument('$filterConfiguration', new Reference('liip_imagine.filter.configuration'))
                ->setArgument('$imageConfigs', new Parameter('progressive_image.image_configs'));

            $container->register(LiipImagineResponsiveImageUrlGenerator::class)
                ->setArgument('$cacheManager', new Reference('liip_imagine.cache.manager'))
                ->setArgument('$router', new Reference('router'))
                ->setArgument('$uriSigner', new Reference('uri_signer'))
                ->setArgument('$runtimeConfigGenerator', new Reference(LiipImagineRuntimeConfigGenerator::class))
                ->setArgument('$filterConfiguration', new Reference('liip_imagine.filter.configuration'))
                ->setArgument('$requestStack', new Reference('request_stack'))
                ->setArgument('$webpGenerate', new Parameter('liip_imagine.webp.generate'))
                ->setPublic(true);
			$container->register(LiipImagineController::class)
				->setArgument('$signer', new Reference('uri_signer'))
				->setArgument('$filterService', new Reference('liip_imagine.service.filter'))
				->setArgument('$dataManager', new Reference('liip_imagine.data.manager'))
				->setArgument('$filterConfiguration', new Reference('liip_imagine.filter.configuration'))
				->setArgument('$controllerConfig', new Reference('liip_imagine.controller.config'))
				->setArgument('$runtimeConfigGenerator', new Reference(LiipImagineRuntimeConfigGenerator::class))
				->setArgument('$metadataReader', new Reference(MetadataReader::class))
				->setArgument('$cache', $imageCacheServiceReference)
				->setPublic(true);

            $container->setAlias(ResponsiveImageUrlGeneratorInterface::class, LiipImagineResponsiveImageUrlGenerator::class)->setPublic(true);
            $container->setAlias(LiipImagineRuntimeConfigGeneratorInterface::class, LiipImagineRuntimeConfigGenerator::class)->setPublic(true);
        }
        $responsiveConfig = $configs['responsive_strategy'] ?? [];
        $generatorId = $responsiveConfig['generator'] ?? null;

        if ($generatorId || class_exists(LiipImagineBundle::class) || isset($responsiveConfig['grid'])) {
            if (!$generatorId && !class_exists(LiipImagineBundle::class)) {
                // We need some default URL generator if LiipImagine is not present but we want to use ResponsiveAttributeGenerator
                $container->register('progressive_image.url_generator.default', \Tito10047\ProgressiveImageBundle\UrlGenerator\DefaultResponsiveImageUrlGenerator::class)
                    ->setPublic(true);
                $container->setAlias(ResponsiveImageUrlGeneratorInterface::class, 'progressive_image.url_generator.default')->setPublic(true);
            }

            $container->register(ResponsiveAttributeGenerator::class, ResponsiveAttributeGenerator::class)
                ->setArgument('$gridConfig', $responsiveConfig['grid'] ?? [])
                ->setArgument('$ratioConfig', $responsiveConfig['ratios'] ?? [])
                ->setArgument('$retinaMultipliers', $retinaMultipliers)
                ->setArgument('$preloadCollector', new Reference(PreloadCollector::class))
                ->setArgument('$urlGenerator', $generatorId ? new Reference($generatorId) : new Reference(ResponsiveImageUrlGeneratorInterface::class))
                ->setArgument('$modifierProvider', new Reference(ModifierProvider::class))
                ->setPublic(true)
            ;
        }

        $container->register(GenerateCustomCssCommand::class)
            ->setArgument('$gridConfig', $responsiveConfig['grid'] ?? [])
            ->setArgument('$projectDir', new Parameter('kernel.project_dir'))
            ->addTag('console.command');

        $container->register(Image::class, Image::class)
            ->setArgument('$analyzer', new Reference(MetadataReader::class))
            ->setArgument('$pathDecorator', array_map(fn ($id) => new Reference($id), $configs['path_decorators'] ?? []))
            ->setArgument('$responsiveAttributeGenerator', $generatorId || class_exists(LiipImagineBundle::class) || isset($responsiveConfig['grid']) ? new Reference(ResponsiveAttributeGenerator::class) : null)
            ->setArgument('$preloadCollector', new Reference(PreloadCollector::class))
            ->setArgument('$framework', $configs['responsive_strategy']['grid']['framework'] ?? 'custom')
            ->setArgument('$defaultRetina', $retina)
            ->setShared(false)
            ->addTag('twig.component')
            ->setPublic(true);

        // The Variant pipeline, when configured, always wins over Liip/default URL
        // generation — opting into variant_store.storage is an explicit signal to use it.
        if (null !== ($configs['variant_store']['storage'] ?? null)) {
            $container->setAlias(ResponsiveImageUrlGeneratorInterface::class, VariantResponsiveImageUrlGenerator::class)->setPublic(true);
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private function configureResolvers(array $config, ContainerBuilder $container): void
    {
        $resolvers = $config['resolvers'] ?? [];
        foreach ($resolvers as $name => $resolverConfig) {
            $id = 'progressive_image.resolver.'.$name;

            if ('filesystem' === $resolverConfig['type']) {
                $container->register($id, FileSystemResolver::class)
                    ->setArgument('$roots', $resolverConfig['roots'] ?? ['%kernel.project_dir%/public'])
                    ->setArgument('$allowUnresolvable', $resolverConfig['allowUnresolvable'] ?? true)
                    ->setPublic(true);
            } elseif ('asset_mapper' === $resolverConfig['type']) {
                $container->register($id, AssetMapperResolver::class)
                    ->setArgument('$assetMapper', new Reference('asset_mapper'))
                    ->setPublic(true);
            } elseif ('chain' === $resolverConfig['type']) {
                $childResolvers = array_map(fn ($name) => new Reference('progressive_image.resolver.'.$name), $resolverConfig['resolvers'] ?? []);
                $container->register($id, ChainResolver::class)
                    ->setArgument('$resolvers', $childResolvers)
                    ->setPublic(true);
            }
        }

        $resolver = $config['resolver'] ?? 'default';

        if (isset($resolvers[$resolver])) {
            // A resolver literally named "default" is already registered under
            // "progressive_image.resolver.default" by the loop above — aliasing it to
            // itself would be a circular reference.
            if ('default' !== $resolver) {
                $container->setAlias('progressive_image.resolver.default', 'progressive_image.resolver.'.$resolver);
            }
        } elseif (in_array($resolver, ['filesystem', 'asset_mapper'])) {
            $container->setAlias('progressive_image.resolver.default', 'progressive_image.resolver.'.$resolver);
        } elseif (!empty($resolvers) && 'default' === $resolver) {
            $firstResolver = array_key_first($resolvers);
            $container->setAlias('progressive_image.resolver.default', 'progressive_image.resolver.'.$firstResolver);
        } else {
            $container->register('progressive_image.resolver.default', FileSystemResolver::class)
                ->setArgument('$roots', ['%kernel.project_dir%/public'])
                ->setArgument('$allowUnresolvable', true);
        }
    }

    /**
     * @param array<string, mixed> $configs
     */
    private function configureVariantContext(array $configs, ContainerBuilder $container): void
    {
        $container->setParameter('progressive_image.variant.filter_sets', $configs['filter_sets'] ?? []);

        $storageId = $configs['variant_store']['storage'] ?? null;
        if (null === $storageId) {
            // Not opted into the new pipeline yet — filter_sets is still validated above so
            // a typo is caught even before variant_store.storage is configured.
            return;
        }

        $secret = $configs['secret'] ?? '%kernel.secret%';
        $defaultFormat = OutputFormat::from($configs['formats']['default'] ?? 'jpeg');
        $fallback = $this->fallbackStrategy($configs);

        // Domain
        $container->register(VariantIdHasher::class)
            ->setArgument('$secret', $secret);
        $container->register(AspectCropCalculator::class);
        // A Quality instance can't be a literal service argument — Symfony's debug
        // container dumper only supports scalars/arrays/enums as literal values, not
        // arbitrary value objects — so it's its own tiny service instead.
        $container->register('progressive_image.variant.default_quality', Quality::class)
            ->setArgument('$value', $configs['formats']['default_quality'] ?? 85);

        // Application
        $container->register(FilterFactory::class);
        $container->register(FilterSetRegistry::class)
            ->setArgument('$rawFilterSets', new Parameter('progressive_image.variant.filter_sets'))
            ->setArgument('$filterFactory', new Reference(FilterFactory::class));
        $container->register(VariantSpecFactory::class)
            ->setArgument('$filterSets', new Reference(FilterSetRegistry::class))
            ->setArgument('$filterFactory', new Reference(FilterFactory::class))
            ->setArgument('$cropCalculator', new Reference(AspectCropCalculator::class))
            ->setArgument('$imageConfigs', $configs['image_configs'] ?? [])
            ->setArgument('$defaultFormat', $defaultFormat)
            ->setArgument('$defaultQuality', new Reference('progressive_image.variant.default_quality'));
        $container->register(PendingGenerationTracker::class)
            ->addTag('kernel.reset', ['method' => 'reset']);

        $this->configureVariantStorage($configs, $container, $storageId);
        $this->configureVariantLock($configs, $container);
        $this->configureVariantDispatcher($configs, $container);

        $container->register(ResolverChainSourceReader::class)
            ->setArgument('$resolver', new Reference('progressive_image.resolver.default'))
            ->setArgument('$loader', new Reference('progressive_image.filesystem.loader'));
        $container->setAlias(SourceReader::class, ResolverChainSourceReader::class);

        $driverClass = 'imagick' === ($configs['driver'] ?? 'gd') ? ImagickDriver::class : GdDriver::class;
        $container->register('progressive_image.variant.image_manager', ImageManager::class)
            ->setArgument('$driver', $driverClass);
        $container->register(InterventionImageManipulator::class)
            ->setArgument('$imageManager', new Reference('progressive_image.variant.image_manager'))
            ->setArgument('$sourceReader', new Reference(SourceReader::class));
        $container->setAlias(ImageManipulator::class, InterventionImageManipulator::class);

        $container->register(SymfonyUriSigner::class)
            ->setArgument('$signer', new Reference('uri_signer'));
        $container->setAlias(UrlSigner::class, SymfonyUriSigner::class);

        $container->register(SymfonyDomainEventBus::class)
            ->setArgument('$dispatcher', new Reference('event_dispatcher'));
        $container->setAlias(DomainEventBus::class, SymfonyDomainEventBus::class);

        $container->register(SystemClock::class);
        $container->setAlias(Clock::class, SystemClock::class);

        $container->register(DefaultOriginalUrlResolver::class);
        $container->setAlias(OriginalUrlResolver::class, DefaultOriginalUrlResolver::class);

        $container->register(QueryPendingUrlBuilder::class)
            ->setArgument('$urlGenerator', new Reference('router'));
        $container->setAlias(PendingUrlBuilder::class, QueryPendingUrlBuilder::class);

        $container->register(GenerateVariantHandler::class)
            ->setArgument('$hasher', new Reference(VariantIdHasher::class))
            ->setArgument('$lock', new Reference(GenerationLock::class))
            ->setArgument('$storage', new Reference(VariantStorage::class))
            ->setArgument('$sourceReader', new Reference(SourceReader::class))
            ->setArgument('$manipulator', new Reference(ImageManipulator::class))
            ->setArgument('$postProcessors', new TaggedIteratorArgument('progressive_image.variant.post_processor'))
            ->setArgument('$eventBus', new Reference(DomainEventBus::class))
            ->setArgument('$clock', new Reference(Clock::class))
            ->setArgument('$failMarkerTtlSeconds', $configs['variant_store']['fail_marker_ttl'] ?? 300);

        $container->register(ResolveVariantUrlHandler::class)
            ->setArgument('$specFactory', new Reference(VariantSpecFactory::class))
            ->setArgument('$hasher', new Reference(VariantIdHasher::class))
            ->setArgument('$storage', new Reference(VariantStorage::class))
            ->setArgument('$tracker', new Reference(PendingGenerationTracker::class))
            ->setArgument('$dispatcher', new Reference(GenerationDispatcher::class))
            ->setArgument('$originalUrlResolver', new Reference(OriginalUrlResolver::class))
            ->setArgument('$pendingUrlBuilder', new Reference(PendingUrlBuilder::class))
            ->setArgument('$urlSigner', new Reference(UrlSigner::class))
            ->setArgument('$fallback', $fallback)
            ->setPublic(true)
            ->setShared(false);

        $container->register(VariantResponsiveImageUrlGenerator::class)
            ->setArgument('$resolveHandler', new Reference(ResolveVariantUrlHandler::class))
            ->setArgument('$metadataReader', new Reference(MetadataReader::class))
            ->setPublic(true);

        $container->register(ImageVariantController::class)
            ->setArgument('$specFactory', new Reference(VariantSpecFactory::class))
            ->setArgument('$hasher', new Reference(VariantIdHasher::class))
            ->setArgument('$storage', new Reference(VariantStorage::class))
            ->setArgument('$generateHandler', new Reference(GenerateVariantHandler::class))
            ->setArgument('$originalUrlResolver', new Reference(OriginalUrlResolver::class))
            ->setArgument('$urlSigner', new Reference(UrlSigner::class))
            ->setArgument('$logger', new Reference('logger', ContainerBuilder::IGNORE_ON_INVALID_REFERENCE))
            ->setPublic(true);

        $container->register(ResponseCacheOverrideListener::class)
            ->setArgument('$tracker', new Reference(PendingGenerationTracker::class))
            ->addTag('kernel.event_listener', ['event' => 'kernel.response', 'method' => '__invoke', 'priority' => -1024]);
    }

    /**
     * @param array<string, mixed> $configs
     */
    private function fallbackStrategy(array $configs): PendingFallbackStrategy
    {
        return 'wait' === ($configs['generation']['fallback_while_pending'] ?? 'original')
            ? PendingFallbackStrategy::Wait
            : PendingFallbackStrategy::Original;
    }

    /**
     * @param array<string, mixed> $configs
     */
    private function configureVariantStorage(array $configs, ContainerBuilder $container, string $storageId): void
    {
        $container->register(FlysystemVariantStorage::class)
            ->setArgument('$filesystem', new Reference($storageId))
            ->setArgument('$prefix', $configs['variant_store']['prefix'] ?? '')
            ->setArgument('$publicUrlPrefix', $configs['variant_store']['public_url_prefix'] ?? '/media/pgi');
        $container->setAlias(VariantStorage::class, FlysystemVariantStorage::class);
    }

    /**
     * @param array<string, mixed> $configs
     */
    private function configureVariantLock(array $configs, ContainerBuilder $container): void
    {
        $lockStoreDsn = $configs['generation']['lock_store'] ?? null;

        if (null === $lockStoreDsn) {
            $container->register('progressive_image.variant.lock_store', FlockStore::class)
                ->setArgument('$lockPath', '%kernel.cache_dir%/pgi-locks');
        } else {
            $container->register('progressive_image.variant.lock_store', StoreFactory::class)
                ->setFactory([StoreFactory::class, 'createStore'])
                ->setArguments([$lockStoreDsn]);
        }

        $container->register('progressive_image.variant.lock_factory', LockFactory::class)
            ->setArgument('$store', new Reference('progressive_image.variant.lock_store'));

        $container->register(SymfonyGenerationLock::class)
            ->setArgument('$lockFactory', new Reference('progressive_image.variant.lock_factory'));
        $container->setAlias(GenerationLock::class, SymfonyGenerationLock::class);
    }

    /**
     * @param array<string, mixed> $configs
     */
    private function configureVariantDispatcher(array $configs, ContainerBuilder $container): void
    {
        $strategy = $configs['generation']['strategy'] ?? 'async';

        if ('sync' === $strategy) {
            $container->register(SyncGenerationDispatcher::class)
                ->setArgument('$handler', new Reference(GenerateVariantHandler::class));
            $container->setAlias(GenerationDispatcher::class, SyncGenerationDispatcher::class);

            return;
        }

        if ('terminate' === $strategy) {
            $container->register(TerminateGenerationDispatcher::class)
                ->setArgument('$handler', new Reference(GenerateVariantHandler::class))
                ->addTag('kernel.event_listener', ['event' => 'kernel.terminate', 'method' => 'onTerminate']);
            $container->setAlias(GenerationDispatcher::class, TerminateGenerationDispatcher::class);

            return;
        }

        if (!interface_exists(MessageBusInterface::class)) {
            throw new \LogicException('progressive_image.generation.strategy is "async" but symfony/messenger is not installed. Run "composer require symfony/messenger", or set the strategy to "sync" or "terminate".');
        }

        $container->register(MessengerGenerationDispatcher::class)
            ->setArgument('$bus', new Reference('message_bus'))
            ->setArgument('$hasher', new Reference(VariantIdHasher::class));
        $container->setAlias(GenerationDispatcher::class, MessengerGenerationDispatcher::class);

        $container->register(GenerateVariantMessageHandler::class)
            ->setArgument('$handler', new Reference(GenerateVariantHandler::class))
            ->addTag('messenger.message_handler', ['handles' => GenerateVariant::class]);
    }
}
