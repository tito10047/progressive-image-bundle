<?php

namespace Tito10047\ProgressiveImageBundle;

use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Contracts\Cache\CacheInterface;
use Tito10047\AltchaBundle\DependencyInjection\AltchaExtension;
use Tito10047\ProgressiveImageBundle\Analyzer\GdImageAnalyzer;
use Tito10047\ProgressiveImageBundle\Analyzer\ImagickAnalyzer;
use Tito10047\ProgressiveImageBundle\DependencyInjection\ProgressiveImageExtension;
use Tito10047\ProgressiveImageBundle\Resolver\AssetMapperResolver;
use Tito10047\ProgressiveImageBundle\Resolver\FileSystemResolver;
use Tito10047\ProgressiveImageBundle\Service\MetadataReader;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Tito10047\ProgressiveImageBundle\Twig\Components\Image;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

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

	public function getContainerExtension(): ?ExtensionInterface
	{
		return new ProgressiveImageExtension ();
	}

}