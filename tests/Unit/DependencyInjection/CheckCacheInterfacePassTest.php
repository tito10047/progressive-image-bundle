<?php

/*
 * This file is part of the Progressive Image Bundle.
 *
 * (c) Jozef Môstka <https://github.com/tito10047/progressive-image-bundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tito10047\ProgressiveImageBundle\Tests\Unit\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Tito10047\ProgressiveImageBundle\DependencyInjection\CheckCacheInterfacePass;

class CheckCacheInterfacePassTest extends TestCase
{
    public function testFactoryDefinedCacheServiceWithoutTaggableTagIsRejected(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('progressive_image.image_cache_enabled', true);

        // No class can be determined at compile time for a factory-defined service.
        $definition = new Definition();
        $definition->setFactory(['SomeCacheFactory', 'create']);
        $container->setDefinition('app.custom_cache', $definition);
        $container->setAlias('progressive_image.image_cache_service', 'app.custom_cache');

        $pass = new CheckCacheInterfacePass();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/not "tag aware"/');
        $pass->process($container);
    }

    public function testFactoryDefinedCacheServiceWithTaggableTagIsAccepted(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('progressive_image.image_cache_enabled', true);

        $definition = new Definition();
        $definition->setFactory(['SomeCacheFactory', 'create']);
        $definition->addTag('cache.taggable');
        $container->setDefinition('app.custom_cache', $definition);
        $container->setAlias('progressive_image.image_cache_service', 'app.custom_cache');

        $pass = new CheckCacheInterfacePass();
        $pass->process($container);

        $this->addToAssertionCount(1);
    }

    public function testRejectsArrayAdapterClassWithoutTaggableTag(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('progressive_image.image_cache_enabled', true);

        $definition = new Definition(\Symfony\Component\Cache\Adapter\ArrayAdapter::class);
        $container->setDefinition('app.array_cache', $definition);
        $container->setAlias('progressive_image.image_cache_service', 'app.array_cache');

        $pass = new CheckCacheInterfacePass();

        $this->expectException(\LogicException::class);
        $pass->process($container);
    }

    public function testAcceptsFilesystemAdapterClassWithTaggableTag(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('progressive_image.image_cache_enabled', true);

        $definition = new Definition(\Symfony\Component\Cache\Adapter\FilesystemAdapter::class);
        $definition->addTag('cache.taggable');
        $container->setDefinition('app.fs_cache', $definition);
        $container->setAlias('progressive_image.image_cache_service', 'app.fs_cache');

        $pass = new CheckCacheInterfacePass();
        $pass->process($container);

        $this->addToAssertionCount(1);
    }
}
