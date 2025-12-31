<?php

namespace Tito10047\ProgressiveImageBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Contracts\Cache\CacheInterface;
use Tito10047\ProgressiveImageBundle\Resolver\AssetMapperResolver;
use Tito10047\ProgressiveImageBundle\Resolver\FileSystemResolver;
use Tito10047\ProgressiveImageBundle\Service\MetadataReader;
use Tito10047\ProgressiveImageBundle\Service\PreloadCollector;
use Tito10047\ProgressiveImageBundle\Twig\Components\Image;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

class ProgressiveImageExtension extends Extension implements PrependExtensionInterface {

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
							$newSetName = $setName . '_' . $breakpointName;
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



	public function load(array $configs, ContainerBuilder $container): void {

		$configs = $this->processConfiguration(new Configuration(), $configs);

		if (!isset($container->getParameter('kernel.bundles')['TwigBundle'])) {
			throw new \LogicException('The TwigBundle is not registered in your application. Try running "composer require symfony/twig-bundle".');
		}

		$loader = new PhpFileLoader($container, new FileLocator(__DIR__.'/../../config'));
		$loader->load('services.php');

		$this->configureResolvers($configs, $container);

		$driver = $configs['driver'] ?? 'gd';
		$analyzerId = match ($driver) {
			"imagick" => "progressive_image.analyzer.imagick",
			"gd" => "progressive_image.analyzer.gd",
			default => $driver,
		};

		$resolver   = $configs['resolver']??'default';
		$maybeService = 'progressive_image.resolver.' . $resolver;
		if ($container->hasDefinition($maybeService)) {
			$resolverId = $maybeService;
		}else{
			$resolverId = $resolver;
		}
		$loaderId = $configs['loader']??'progressive_image.filesystem.loader';
		$cacheId = $configs['cache']??CacheInterface::class;

		$definition = $container->getDefinition(MetadataReader::class);
		$definition->setArgument('$analyzer', new Reference($analyzerId))
			->setArgument('$loader', new Reference($loaderId))
			->setArgument('$pathResolver', new Reference($resolverId))
			->setArgument('$cache', new Reference($cacheId))
		;

		if (isset($configs['ttl'])) {
			$definition->setArgument('$ttl', $configs['ttl']);
		}

		if (isset($configs['fallback_image'])) {
			$definition->setArgument('$fallbackPath', $configs['fallback_image']);
		}

		$responsiveConfig = $configs['responsive_strategy'] ?? [];
		$breakpoints = $responsiveConfig['breakpoints'] ?? [];
		$defaultPreset = [
			'widths' => $responsiveConfig['fallback_widths'] ?? [],
			'sizes' => $responsiveConfig['fallback_sizes'] ?? '100vw',
		];
		$presets = $responsiveConfig['presets'] ?? [];
		$generatorId = $responsiveConfig['generator'] ?? null;

		$container->register(Image::class, Image::class)
			->setArgument(0, new Reference(MetadataReader::class))
			->setArgument(1, array_map(fn($id) => new Reference($id), $configs['path_decorators'] ?? []))
			->setArgument(2, new Reference(PreloadCollector::class))
			->setArgument(3, $generatorId ? new Reference($generatorId) : null)
			->setArgument(4, $breakpoints)
			->setArgument(5, $defaultPreset)
			->setArgument(6, $presets)
            ->setShared(false)
			->addTag('twig.component')
			->setPublic(true);


	}


	private function configureResolvers(array $config, ContainerBuilder $container): void
	{
		$resolvers = $config['resolvers'] ?? [];
		foreach ($resolvers as $name => $resolverConfig) {
			$id = 'progressive_image.resolver.' . $name;

			if ('filesystem' === $resolverConfig['type']) {
				$container->register($id, FileSystemResolver::class)
					->setArgument('$roots', $resolverConfig['roots'] ?? ["%kernel.project_dir%/public"])
					->setArgument('$allowUnresolvable', $resolverConfig['allowUnresolvable'] ?? false);
			} elseif ('asset_mapper' === $resolverConfig['type']) {
				$container->register($id, AssetMapperResolver::class);
			}
			// Chain resolver logic can be added here if needed
		}

		if (isset($config['resolver']) && !isset($resolvers[$config['resolver']])) {
			// If a default resolver type is used but not defined in resolvers array
			if (in_array($config['resolver'], ['filesystem', 'asset_mapper'])) {
				// handle basic types if they are used as string directly
			}
		}

		// Register a default alias if possible
		if (isset($config['resolver']) && isset($resolvers[$config['resolver']])) {
			$container->setAlias('progressive_image.resolver.default', 'progressive_image.resolver.' . $config['resolver']);
		} elseif (!empty($resolvers)) {
			$firstResolver = array_key_first($resolvers);
			$container->setAlias('progressive_image.resolver.default', 'progressive_image.resolver.' . $firstResolver);
		} else {
			// Fallback if no resolvers defined, register a basic one to avoid ServiceNotFoundException
			$container->register('progressive_image.resolver.default', FileSystemResolver::class)
				->setArgument('$roots', ['%kernel.project_dir%/public'])
				->setArgument('$allowUnresolvable', true);
		}
	}
}