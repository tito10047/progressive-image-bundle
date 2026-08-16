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
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;
use Symfony\Component\Lock\Store\StoreFactory;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Tito10047\ProgressiveImageBundle\Command\GenerateCustomCssCommand;
use Tito10047\ProgressiveImageBundle\Event\TransparentImageCacheSubscriber;
use Tito10047\ProgressiveImageBundle\Modifier\BaseFilterModifier;
use Tito10047\ProgressiveImageBundle\Modifier\FilterModifierInterface;
use Tito10047\ProgressiveImageBundle\Modifier\ModifierInterface;
use Tito10047\ProgressiveImageBundle\Modifier\ModifierProvider;
use Tito10047\ProgressiveImageBundle\Resolver\AssetMapperResolver;
use Tito10047\ProgressiveImageBundle\Resolver\ChainResolver;
use Tito10047\ProgressiveImageBundle\Resolver\FileSystemResolver;
use Tito10047\ProgressiveImageBundle\Service\MetadataReader;
use Tito10047\ProgressiveImageBundle\Service\PreloadCollector;
use Tito10047\ProgressiveImageBundle\Service\ResponsiveAttributeGenerator;
use Tito10047\ProgressiveImageBundle\Twig\Components\Image;
use Tito10047\ProgressiveImageBundle\Twig\TransparentCacheExtension;
use Tito10047\ProgressiveImageBundle\UrlGenerator\DefaultResponsiveImageUrlGenerator;
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
use Tito10047\ProgressiveImageBundle\Variant\Domain\Port\SourceReader;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Port\VariantStorage;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Service\AspectCropCalculator;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Service\VariantIdHasher;
use Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Flysystem\FlysystemVariantStorage;
use Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Intervention\InterventionImageManipulator;
use Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Lock\SymfonyGenerationLock;
use Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Messenger\GenerateVariantMessageHandler;
use Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Messenger\MessengerGenerationDispatcher;
use Tito10047\ProgressiveImageBundle\Variant\Infrastructure\PostProcess\AvifencPostProcessor;
use Tito10047\ProgressiveImageBundle\Variant\Infrastructure\PostProcess\CwebpPostProcessor;
use Tito10047\ProgressiveImageBundle\Variant\Infrastructure\PostProcess\JpegoptimPostProcessor;
use Tito10047\ProgressiveImageBundle\Variant\Infrastructure\PostProcess\PngquantPostProcessor;
use Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Presentation\Controller\ImageVariantController;
use Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Presentation\EventListener\ResponseCacheOverrideListener;
use Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Presentation\UrlGenerator\QueryPendingUrlBuilder;
use Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Presentation\UrlGenerator\VariantResponsiveImageUrlGenerator;
use Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Source\ChainSourceReader;
use Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Source\HttpSourceReader;
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

        // generation.transport ("only used if strategy=async" per its own ->info()) is
        // otherwise never actually wired anywhere: without this, GenerateVariant has no
        // framework.messenger.routing entry, so the default bus falls back to handling it
        // synchronously in-process — the "async" strategy would silently behave like "sync".
        $resolved = (new Processor())->processConfiguration(new Configuration(), $builder->getExtensionConfig('progressive_image'));
        if ('async' === $resolved['generation']['strategy'] && interface_exists(MessageBusInterface::class)) {
            $builder->prependExtensionConfig('framework', [
                'messenger' => [
                    'routing' => [
                        GenerateVariant::class => $resolved['generation']['transport'],
                    ],
                ],
            ]);
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

        $qualityByFormat = $configs['formats']['quality'] ?? [];
        $pictureFormats = [];
        foreach ($configs['formats']['picture'] ?? [] as $pictureFormat) {
            $pictureFormats[$pictureFormat] = [
                'mime' => OutputFormat::from($pictureFormat)->mime(),
                'quality' => $qualityByFormat[$pictureFormat] ?? null,
            ];
        }

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
            ->setArgument('$modifiers', new TaggedIteratorArgument('progressive_image.modifier'));

        $container->register(BaseFilterModifier::class)
            ->addTag('progressive_image.modifier', ['priority' => -100]);

        $responsiveConfig = $configs['responsive_strategy'] ?? [];
        $generatorId = $responsiveConfig['generator'] ?? null;

        // responsive_strategy.grid always has a value here: both `responsive_strategy` and
        // its `grid` child use addDefaultsIfNotSet() in Configuration, so this array key is
        // always present after processConfiguration() — there is no "unconfigured" state to
        // gate on. ResponsiveAttributeGenerator (and Image's use of it, below) is therefore
        // always registered.
        //
        // ResponsiveImageUrlGeneratorInterface is public and always aliased to whichever
        // generator is actually in effect (explicit generator > Variant pipeline > default),
        // so any code fetching the interface directly from the container — not just
        // ResponsiveAttributeGenerator's own constructor argument below — sees the same
        // generator. The Variant-pipeline-wins branch further down may still overwrite this
        // alias when no explicit generator is configured.
        if ($generatorId) {
            $container->setAlias(ResponsiveImageUrlGeneratorInterface::class, $generatorId)->setPublic(true);
        } else {
            $container->register('progressive_image.url_generator.default', DefaultResponsiveImageUrlGenerator::class)
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
            ->setArgument('$logger', new Reference('logger', ContainerBuilder::IGNORE_ON_INVALID_REFERENCE))
            ->setArgument('$fluidMaxWidth', $responsiveConfig['fluid_max_width'] ?? 1920)
            ->setArgument('$pictureFormats', $pictureFormats)
            ->setPublic(true)
        ;

        $container->register(GenerateCustomCssCommand::class)
            ->setArgument('$gridConfig', $responsiveConfig['grid'] ?? [])
            ->setArgument('$projectDir', new Parameter('kernel.project_dir'))
            ->addTag('console.command');

        $container->register(Image::class, Image::class)
            ->setArgument('$analyzer', new Reference(MetadataReader::class))
            ->setArgument('$pathDecorator', array_map(fn ($id) => new Reference($id), $configs['path_decorators'] ?? []))
            ->setArgument('$responsiveAttributeGenerator', new Reference(ResponsiveAttributeGenerator::class))
            ->setArgument('$preloadCollector', new Reference(PreloadCollector::class))
            ->setArgument('$framework', $configs['responsive_strategy']['grid']['framework'] ?? 'custom')
            ->setArgument('$defaultRetina', $retina)
            ->setArgument('$logger', new Reference('logger', ContainerBuilder::IGNORE_ON_INVALID_REFERENCE))
            ->setShared(false)
            ->addTag('twig.component')
            ->setPublic(true);

        // The Variant pipeline, when configured, wins over the default URL generator — but
        // an explicit responsive_strategy.generator (a user opting into their own
        // implementation) must still win over the Variant pipeline, matching this option's
        // own ->info() description ("Overrides the default/Variant-pipeline generator").
        if (!$generatorId && null !== ($configs['variant_store']['storage'] ?? null)) {
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
                    ->setArgument('$allowUnresolvable', $resolverConfig['allowUnresolvable'])
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
        } elseif (1 === count($resolvers) && 'default' === $resolver) {
            // Exactly one resolver is configured and none of it is literally named
            // "default": there's no ambiguity in picking it. With two or more resolvers
            // and no "default" among them, silently picking array_key_first() would make
            // the effective resolver depend on YAML key order — fail fast instead below.
            $onlyResolver = array_key_first($resolvers);
            $container->setAlias('progressive_image.resolver.default', 'progressive_image.resolver.'.$onlyResolver);
        } elseif (count($resolvers) > 1 && 'default' === $resolver) {
            throw new \LogicException(sprintf('Multiple "resolvers" are configured (%s) but none is named "default" and no explicit "resolver" option was set. Either name one of them "default", or set progressive_image.resolver to the one that should be used.', implode(', ', array_map(static fn ($name) => sprintf('"%s"', $name), array_keys($resolvers)))));
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
        $this->configurePostProcessors($configs, $container);

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

        $httpSourceConfig = $configs['variant_source']['http'] ?? ['enabled' => false];
        if ($httpSourceConfig['enabled'] ?? false) {
            $this->configureHttpSourceReader($httpSourceConfig, $container);
            $container->setAlias(SourceReader::class, ChainSourceReader::class);
        } else {
            $container->setAlias(SourceReader::class, ResolverChainSourceReader::class);
        }

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
            ->setArgument('$sourceReader', new Reference(SourceReader::class))
            ->setArgument('$requestStack', new Reference('request_stack'))
            ->setArgument('$negotiateFormats', $configs['formats']['negotiate'] ?? [])
            ->setArgument('$qualityByFormat', $configs['formats']['quality'] ?? [])
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
    private function configurePostProcessors(array $configs, ContainerBuilder $container): void
    {
        $processors = [
            'jpegoptim' => JpegoptimPostProcessor::class,
            'pngquant' => PngquantPostProcessor::class,
            'cwebp' => CwebpPostProcessor::class,
            'avifenc' => AvifencPostProcessor::class,
        ];

        foreach ($processors as $name => $class) {
            $config = $configs['post_processors'][$name] ?? ['enabled' => false, 'bin' => $name];
            $enabled = (bool) ($config['enabled'] ?? false);
            $bin = (string) ($config['bin'] ?? $name);

            // ValidatePostProcessorBinariesPass reads these at compile time — a missing
            // binary must break cache:clear, not surface as a generation failure later.
            $container->setParameter('progressive_image.post_processors.'.$name.'.enabled', $enabled);
            $container->setParameter('progressive_image.post_processors.'.$name.'.bin', $bin);

            if (!$enabled) {
                continue;
            }

            $definition = $container->register($class)
                ->setArgument('$bin', $bin)
                ->addTag('progressive_image.variant.post_processor');

            // cwebp/avifenc re-encode via their own CLI binary, discarding Intervention's
            // encoding — without passing the configured quality through, that re-encode
            // would silently use the binary's own default instead.
            if (CwebpPostProcessor::class === $class) {
                $definition->setArgument('$quality', $configs['formats']['quality']['webp'] ?? 82);
            } elseif (AvifencPostProcessor::class === $class) {
                $definition->setArgument('$quality', $configs['formats']['quality']['avif'] ?? 60);
            }
        }
    }

    /**
     * @param array<string, mixed> $configs
     */
    private function configureVariantStorage(array $configs, ContainerBuilder $container, string $storageId): void
    {
        $container->register(FlysystemVariantStorage::class)
            ->setArgument('$filesystem', new Reference($storageId))
            ->setArgument('$prefix', $configs['variant_store']['prefix'] ?? '')
            ->setArgument('$publicUrlPrefix', $configs['variant_store']['public_url_prefix'] ?? '/media/pgi')
            ->setArgument('$logger', new Reference('logger', ContainerBuilder::IGNORE_ON_INVALID_REFERENCE));
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
     * @param array<string, mixed> $httpConfig
     */
    private function configureHttpSourceReader(array $httpConfig, ContainerBuilder $container): void
    {
        if (!interface_exists(HttpClientInterface::class)) {
            throw new \LogicException('progressive_image.variant_source.http.enabled is true but symfony/http-client is not installed. Run "composer require symfony/http-client".');
        }

        // Deliberately its own HttpClient::create() instance rather than reusing the app's
        // "http_client" service (which requires framework.http_client to be configured at
        // all) — the Variant pipeline stays self-sufficient the same way it owns its own
        // ImageManager and lock store instead of depending on app-level config for those.
        $container->register('progressive_image.variant.http_client', HttpClientInterface::class)
            ->setFactory([HttpClient::class, 'create']);

        $container->register(HttpSourceReader::class)
            ->setArgument('$client', new Reference('progressive_image.variant.http_client'))
            ->setArgument('$allowedHosts', $httpConfig['allowed_hosts'] ?? [])
            ->setArgument('$timeoutSeconds', $httpConfig['timeout'] ?? 5);

        $container->register(ChainSourceReader::class)
            ->setArgument('$local', new Reference(ResolverChainSourceReader::class))
            ->setArgument('$remote', new Reference(HttpSourceReader::class));
    }

    /**
     * @param array<string, mixed> $configs
     */
    private function configureVariantDispatcher(array $configs, ContainerBuilder $container): void
    {
        $strategy = $configs['generation']['strategy'] ?? 'async';

        if ('sync' === $strategy) {
            $container->register(SyncGenerationDispatcher::class)
                ->setArgument('$handler', new Reference(GenerateVariantHandler::class))
                ->setArgument('$logger', new Reference('logger', ContainerBuilder::IGNORE_ON_INVALID_REFERENCE));
            $container->setAlias(GenerationDispatcher::class, SyncGenerationDispatcher::class);

            return;
        }

        if ('terminate' === $strategy) {
            // NOT setShared(false): unlike ResolveVariantUrlHandler, this class is both
            // injected via the GenerationDispatcher alias AND separately resolved by the
            // kernel.event_listener tag to call onTerminate() — a non-shared registration
            // would make those resolve to two different instances, so onTerminate() would
            // flush an empty queue instead of the one dispatch() actually populated
            // (verified: this reproduces as a real failure, not just a theoretical one).
            $container->register(TerminateGenerationDispatcher::class)
                ->setArgument('$handler', new Reference(GenerateVariantHandler::class))
                ->setArgument('$logger', new Reference('logger', ContainerBuilder::IGNORE_ON_INVALID_REFERENCE))
                ->addTag('kernel.event_listener', ['event' => 'kernel.terminate', 'method' => 'onTerminate']);
            $container->setAlias(GenerationDispatcher::class, TerminateGenerationDispatcher::class);

            return;
        }

        if (!interface_exists(MessageBusInterface::class)) {
            throw new \LogicException('progressive_image.generation.strategy is "async" but symfony/messenger is not installed. Run "composer require symfony/messenger", or set the strategy to "sync" or "terminate".');
        }

        $container->register(MessengerGenerationDispatcher::class)
            ->setArgument('$bus', new Reference('messenger.default_bus'))
            ->setArgument('$hasher', new Reference(VariantIdHasher::class))
            ->setShared(false);
        $container->setAlias(GenerationDispatcher::class, MessengerGenerationDispatcher::class);

        $container->register(GenerateVariantMessageHandler::class)
            ->setArgument('$handler', new Reference(GenerateVariantHandler::class))
            ->addTag('messenger.message_handler', ['handles' => GenerateVariant::class]);
    }
}
