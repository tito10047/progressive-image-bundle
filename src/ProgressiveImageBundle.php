<?php

namespace Tito10047\ProgressiveImageBundle;

use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Contracts\Cache\CacheInterface;
use Tito10047\ProgressiveImageBundle\Analyzer\GdImageAnalyzer;
use Tito10047\ProgressiveImageBundle\Analyzer\ImagickAnalyzer;
use Tito10047\ProgressiveImageBundle\DependencyInjection\Compiler\ProgressiveImageBundleCompilerPass;
use Tito10047\ProgressiveImageBundle\DependencyInjection\ProgressiveImageExtension;
use Tito10047\ProgressiveImageBundle\Resolver\AssetMapperResolver;
use Tito10047\ProgressiveImageBundle\Resolver\FileSystemResolver;
use Tito10047\ProgressiveImageBundle\Service\MetadataReader;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * @link https://symfony.com/doc/current/bundles/best_practices.html
 */
class ProgressiveImageBundle extends AbstractBundle
{

	protected string $extensionAlias = 'progressive_image';
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->import('../config/definition.php');
    }

    
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
		if (!isset($builder->getParameter('kernel.bundles')['TwigBundle'])) {
			throw new LogicException('The TwigBundle is not registered in your application. Try running "composer require symfony/twig-bundle".');
		}
        $container->import('../config/services.php');

        $this->configureResolvers($config, $container);

        $driver = $config['driver'] ?? 'gd';
		$analyzerId = match ($driver) {
			"imagick" => "progressive_image.analyzer.imagick",
			"gd" => "progressive_image.analyzer.gd",
			default => $driver,
		};

		$resolver   = $config['resolver']??'filesystem';
		$maybeService = 'progressive_image.resolver.' . $resolver;
		if ($builder->hasDefinition($maybeService)) {
			$resolverId = $maybeService;
		}else{
			$resolverId = $resolver;
		}
		$loaderId = $config['loader']??'progressive_image.filesystem.loader';
		$cacheId = $config['cache']??CacheInterface::class;

        $container->services()
            ->get(MetadataReader::class)
            ->arg('$analyzer', new Reference($analyzerId))
            ->arg('$loader', new Reference($loaderId))
            ->arg('$pathResolver', new Reference($resolverId))
			->arg('$cache', new Reference($cacheId))
        ;

        if (isset($config['ttl'])) {
            $container->services()->get(MetadataReader::class)->arg('$ttl', $config['ttl']);
        }

        if (isset($config['fallback_image'])) {
            $container->services()->get(MetadataReader::class)->arg('$fallbackPath', $config['fallback_image']);
        }


			// 3. "Vložíme" našu konfiguráciu priamo do TwigComponentBundle
			$builder->prependExtensionConfig('twig_component', [
				'defaults' => [
					'EasyCorp\\Bundle\\EasyAdminBundle\\Twig\\Component\\' => [
						'template_directory' => '@PgImage/components/',
						'name_prefix' => 'pgi',
					],
				],
			]);
    }

    private function configureResolvers(array $config, ContainerConfigurator $container): void
    {
        $resolvers = $config['resolvers'] ?? [];
        foreach ($resolvers as $name => $resolverConfig) {
            $id = 'progressive_image.resolver.' . $name;
            $services = $container->services();

            if ('filesystem' === $resolverConfig['type']) {
                $services->set($id, FileSystemResolver::class)
                    ->arg('$roots', $resolverConfig['roots'] ?? [])
                    ->arg('$allowUnresolvable', $resolverConfig['allowUnresolvable'] ?? false);
            } elseif ('asset_mapper' === $resolverConfig['type']) {
                $services->set($id, AssetMapperResolver::class);
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
            $container->services()->alias('progressive_image.resolver.default', 'progressive_image.resolver.' . $config['resolver']);
        } elseif (!empty($resolvers)) {
            $firstResolver = array_key_first($resolvers);
            $container->services()->alias('progressive_image.resolver.default', 'progressive_image.resolver.' . $firstResolver);
        } else {
             // Fallback if no resolvers defined, register a basic one to avoid ServiceNotFoundException
             $container->services()->set('progressive_image.resolver.default', FileSystemResolver::class)
                 ->arg('$roots', ['%kernel.project_dir%/public'])
                 ->arg('$allowUnresolvable', true);
        }
    }

	public function getNamespace(): string {
		return 'PgImage';
	}

}