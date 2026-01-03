<?php

/*
 * This file is part of the Progressive Image Bundle.
 *
 * (c) Jozef Môstka <https://github.com/tito10047/progressive-image-bundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tito10047\ProgressiveImageBundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Tito10047\ProgressiveImageBundle\DependencyInjection\CheckCacheInterfacePass;
use Tito10047\ProgressiveImageBundle\DependencyInjection\ProgressiveImageExtension;

/**
 * @see https://symfony.com/doc/current/bundles/best_practices.html
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
