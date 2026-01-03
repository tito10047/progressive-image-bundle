<?php

namespace Tito10047\ProgressiveImageBundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Tito10047\ProgressiveImageBundle\DependencyInjection\CheckCacheInterfacePass;
use Tito10047\ProgressiveImageBundle\DependencyInjection\ProgressiveImageExtension;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * @link https://symfony.com/doc/current/bundles/best_practices.html
 */
final class ProgressiveImageBundle extends AbstractBundle
{
	public const STIMULUS_CONTROLLER = 'tito10047--progressive-image-bundle--progressive-image';

	protected string $extensionAlias = 'progressive_image';

	public function build(ContainerBuilder $container): void
	{
		parent::build($container);
		$container->addCompilerPass(new CheckCacheInterfacePass());
	}

	public function getContainerExtension(): ?ExtensionInterface
	{
		return new ProgressiveImageExtension();
	}

}