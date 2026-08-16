<?php

/*
 * This file is part of the Progressive Image Bundle.
 *
 * (c) Jozef Môstka <https://github.com/tito10047/progressive-image-bundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tito10047\ProgressiveImageBundle\Tests\Unit\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Tito10047\ProgressiveImageBundle\DependencyInjection\ProgressiveImageExtension;
use Tito10047\ProgressiveImageBundle\Resolver\AssetMapperResolver;

final class AssetMapperResolverWiringTest extends TestCase
{
    public function testAssetMapperResolverReferencesTheAssetMapperServiceOptionally(): void
    {
        // Reproduces recipes-contrib's "install this recipe on a bare project" check: an
        // "asset_mapper" resolver is configured, but symfony/asset-mapper (and its
        // "asset_mapper" service) is never installed/registered at all. A hard Reference
        // here would make CheckExceptionOnInvalidReferenceBehaviorPass fail the container
        // compile with "non-existent service" on any project without asset-mapper.
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['TwigBundle' => true]);
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());
        $container->setParameter('kernel.cache_dir', sys_get_temp_dir());
        $container->setParameter('kernel.secret', 'test-secret');

        (new ProgressiveImageExtension())->load([
            'progressive_image' => [
                'resolvers' => [
                    'default' => ['type' => 'asset_mapper'],
                ],
            ],
        ], $container);

        $definition = $container->getDefinition('progressive_image.resolver.default');
        self::assertSame(AssetMapperResolver::class, $definition->getClass());

        $reference = $definition->getArgument('$assetMapper');
        self::assertInstanceOf(Reference::class, $reference);
        self::assertSame(ContainerBuilder::IGNORE_ON_INVALID_REFERENCE, $reference->getInvalidBehavior());
    }
}
