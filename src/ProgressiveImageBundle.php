<?php

namespace Tito10047\ProgressiveImageBundle;

use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Tito10047\ProgressiveImageBundle\DependencyInjection\ProgressiveImageExtension;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * @link https://symfony.com/doc/current/bundles/best_practices.html
 */
class ProgressiveImageBundle extends AbstractBundle
{
	public const STIMULUS_CONTROLLER = 'tito10047--progressive-image-bundle--progressive-image';

	protected string $extensionAlias = 'progressive_image';
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->import('../config/definition.php');
    }

	public function getContainerExtension(): ?ExtensionInterface
	{
		return new ProgressiveImageExtension();
	}

}