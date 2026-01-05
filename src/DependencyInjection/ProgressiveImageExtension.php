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

use Liip\ImagineBundle\LiipImagineBundle;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\DependencyInjection\Reference;
use Tito10047\ProgressiveImageBundle\Event\TransparentImageCacheSubscriber;
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
        $container->setAlias('progressive_image.image_cache_service', $imageCacheServiceId);

        $container->register(TransparentCacheExtension::class)
            ->setArgument('$ttl', new Parameter('progressive_image.ttl'))
            ->setArgument('$cache', $imageCacheServiceReference)
            ->addTag('twig.extension')
        ;

        $container->register(TransparentImageCacheSubscriber::class)
            ->setArgument('$enabled', new Parameter('progressive_image.image_cache_enabled'))
            ->setArgument('$cache', $imageCacheServiceReference)
            ->setArgument('$ttl', new Parameter('progressive_image.ttl'))
            ->addTag('kernel.event_subscriber')
        ;

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
                ->setArgument('$cache', $imageCacheServiceReference)
				->setArgument('$webpGenerate', new Parameter('liip_imagine.webp.generate'))
                ->setPublic(true);

			$container->setAlias(ResponsiveImageUrlGeneratorInterface::class, LiipImagineResponsiveImageUrlGenerator::class)->setPublic(true);
			$container->setAlias(LiipImagineRuntimeConfigGeneratorInterface::class, LiipImagineRuntimeConfigGenerator::class)->setPublic(true);
        }
        $responsiveConfig = $configs['responsive_strategy'] ?? [];
        $generatorId = $responsiveConfig['generator'] ?? null;

        if ($generatorId || class_exists(LiipImagineBundle::class)) {
            $container->register(ResponsiveAttributeGenerator::class, ResponsiveAttributeGenerator::class)
                ->setArgument('$gridConfig', $responsiveConfig['grid'] ?? [])
                ->setArgument('$ratioConfig', $responsiveConfig['ratios'] ?? [])
                ->setArgument('$preloadCollector', new Reference(PreloadCollector::class))
                ->setArgument('$urlGenerator', $generatorId ? new Reference($generatorId) : new Reference(ResponsiveImageUrlGeneratorInterface::class))
            ;
        }

        $container->register(Image::class, Image::class)
            ->setArgument('$analyzer', new Reference(MetadataReader::class))
            ->setArgument('$pathDecorator', array_map(fn ($id) => new Reference($id), $configs['path_decorators'] ?? []))
            ->setArgument('$responsiveAttributeGenerator', $generatorId || class_exists(LiipImagineBundle::class) ? new Reference(ResponsiveAttributeGenerator::class) : null)
            ->setArgument('$preloadCollector', new Reference(PreloadCollector::class))
			->setArgument('$framework', $configs['responsive_strategy']['grid']['framework'] ?? 'custom')
            ->setShared(false)
            ->addTag('twig.component')
            ->setPublic(true);
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
					->setArgument('$allowUnresolvable', $resolverConfig['allowUnresolvable'] ?? true);
            } elseif ('asset_mapper' === $resolverConfig['type']) {
				$container->register($id, AssetMapperResolver::class)
					->setArgument('$assetMapper', new Reference('asset_mapper'));
			} elseif ('chain' === $resolverConfig['type']) {
				$childResolvers = array_map(fn($name) => new Reference('progressive_image.resolver.' . $name), $resolverConfig['resolvers'] ?? []);
				$container->register($id, ChainResolver::class)
					->setArgument('$resolvers', $childResolvers);
            }
        }

		$resolver = $config['resolver'] ?? 'default';

		if (isset($resolvers[$resolver])) {
			$container->setAlias('progressive_image.resolver.default', 'progressive_image.resolver.' . $resolver);
		} elseif (in_array($resolver, ['filesystem', 'asset_mapper'])) {
			$container->setAlias('progressive_image.resolver.default', 'progressive_image.resolver.' . $resolver);
		} elseif (!empty($resolvers) && 'default' === $resolver) {
            $firstResolver = array_key_first($resolvers);
            $container->setAlias('progressive_image.resolver.default', 'progressive_image.resolver.'.$firstResolver);
        } else {
            $container->register('progressive_image.resolver.default', FileSystemResolver::class)
                ->setArgument('$roots', ['%kernel.project_dir%/public'])
                ->setArgument('$allowUnresolvable', true);
        }
    }
}
