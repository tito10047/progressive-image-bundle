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
use Tito10047\ProgressiveImageBundle\Command\RemoveVariantCommand;
use Tito10047\ProgressiveImageBundle\Command\WarmVariantCommand;
use Tito10047\ProgressiveImageBundle\DependencyInjection\ProgressiveImageExtension;

final class VariantCommandsWiringTest extends TestCase
{
    private function buildContainer(bool $withVariantStore): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['TwigBundle' => true]);
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());
        $container->setParameter('kernel.cache_dir', sys_get_temp_dir());
        $container->setParameter('kernel.secret', 'test-secret');

        $config = [
            'resolvers' => [
                'default' => ['type' => 'filesystem', 'roots' => ['/tmp']],
            ],
        ];
        if ($withVariantStore) {
            $config['variant_store'] = ['storage' => 'test.variant_storage'];
        }

        (new ProgressiveImageExtension())->load(['progressive_image' => $config], $container);

        return $container;
    }

    public function testBothCommandsAreRegisteredAsConsoleCommandsWhenTheVariantPipelineIsConfigured(): void
    {
        $container = $this->buildContainer(true);

        self::assertTrue($container->hasDefinition(WarmVariantCommand::class));
        self::assertTrue($container->getDefinition(WarmVariantCommand::class)->hasTag('console.command'));
        self::assertTrue($container->hasDefinition(RemoveVariantCommand::class));
        self::assertTrue($container->getDefinition(RemoveVariantCommand::class)->hasTag('console.command'));
    }

    public function testNeitherCommandIsRegisteredWithoutTheVariantPipelineConfigured(): void
    {
        $container = $this->buildContainer(false);

        self::assertFalse($container->hasDefinition(WarmVariantCommand::class));
        self::assertFalse($container->hasDefinition(RemoveVariantCommand::class));
    }
}
