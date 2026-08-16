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
use Tito10047\ProgressiveImageBundle\DependencyInjection\ProgressiveImageExtension;
use Tito10047\ProgressiveImageBundle\Variant\Application\Handler\ResolveFilterUrlHandler;
use Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Presentation\Twig\FilterUrlExtension;

final class FilterUrlExtensionWiringTest extends TestCase
{
    private function buildContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['TwigBundle' => true]);
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());
        $container->setParameter('kernel.cache_dir', sys_get_temp_dir());
        $container->setParameter('kernel.secret', 'test-secret');

        (new ProgressiveImageExtension())->load([
            'progressive_image' => [
                'resolvers' => [
                    'default' => ['type' => 'filesystem', 'roots' => ['/tmp']],
                ],
                'variant_store' => [
                    'storage' => 'test.variant_storage',
                ],
            ],
        ], $container);

        return $container;
    }

    public function testResolveFilterUrlHandlerIsRegisteredNonSharedForMemoizationSafety(): void
    {
        $container = $this->buildContainer();

        self::assertTrue($container->hasDefinition(ResolveFilterUrlHandler::class));
        self::assertFalse($container->getDefinition(ResolveFilterUrlHandler::class)->isShared());
    }

    public function testFilterUrlExtensionIsRegisteredAsATwigExtension(): void
    {
        $container = $this->buildContainer();

        self::assertTrue($container->hasDefinition(FilterUrlExtension::class));
        self::assertTrue($container->getDefinition(FilterUrlExtension::class)->hasTag('twig.extension'));
    }

    public function testNeitherServiceIsRegisteredWithoutTheVariantPipelineConfigured(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['TwigBundle' => true]);
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());
        $container->setParameter('kernel.cache_dir', sys_get_temp_dir());
        $container->setParameter('kernel.secret', 'test-secret');

        (new ProgressiveImageExtension())->load([
            'progressive_image' => [
                'resolvers' => [
                    'default' => ['type' => 'filesystem', 'roots' => ['/tmp']],
                ],
            ],
        ], $container);

        self::assertFalse($container->hasDefinition(ResolveFilterUrlHandler::class));
        self::assertFalse($container->hasDefinition(FilterUrlExtension::class));
    }
}
