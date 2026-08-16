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
use Tito10047\ProgressiveImageBundle\Variant\Application\Service\VariantSpecFactory;

final class FormatsProgressiveWiringTest extends TestCase
{
    private function buildContainer(bool $progressive, bool $stripMetadata): ContainerBuilder
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
                'formats' => [
                    'progressive' => $progressive,
                    'strip_metadata' => $stripMetadata,
                ],
            ],
        ], $container);

        return $container;
    }

    public function testDefaultProgressiveAndStripMetadataAreThreadedIntoVariantSpecFactory(): void
    {
        $container = $this->buildContainer(true, true);

        $definition = $container->getDefinition(VariantSpecFactory::class);
        self::assertTrue($definition->getArgument('$defaultProgressive'));
        self::assertTrue($definition->getArgument('$defaultStripMetadata'));
    }

    public function testFalseValuesAreThreadedThroughToo(): void
    {
        $container = $this->buildContainer(false, false);

        $definition = $container->getDefinition(VariantSpecFactory::class);
        self::assertFalse($definition->getArgument('$defaultProgressive'));
        self::assertFalse($definition->getArgument('$defaultStripMetadata'));
    }
}
