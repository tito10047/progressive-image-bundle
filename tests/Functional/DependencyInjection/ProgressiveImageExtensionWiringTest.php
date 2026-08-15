<?php

/*
 * This file is part of the Progressive Image Bundle.
 *
 * (c) Jozef Môstka <https://github.com/tito10047/progressive-image-bundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tito10047\ProgressiveImageBundle\Tests\Functional\DependencyInjection;

use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Tito10047\ProgressiveImageBundle\Service\ResponsiveAttributeGenerator;
use Tito10047\ProgressiveImageBundle\Tests\Fixtures\FakeDimensionsEchoingUrlGenerator;
use Tito10047\ProgressiveImageBundle\Tests\Integration\PGITestCase;
use Tito10047\ProgressiveImageBundle\UrlGenerator\ResponsiveImageUrlGeneratorInterface;
use Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Presentation\UrlGenerator\VariantResponsiveImageUrlGenerator;

class ProgressiveImageExtensionWiringTest extends PGITestCase
{
    public function testResponsiveAttributeGeneratorIsRegisteredEvenWithoutExplicitResponsiveStrategyConfig(): void
    {
        // responsive_strategy.grid is populated by Configuration's addDefaultsIfNotSet()
        // whether or not the user configures anything, so the service must always exist.
        self::bootKernel([
            'progressive_image' => [
                'resolvers' => [
                    'default' => ['type' => 'filesystem', 'roots' => ['/tmp']],
                ],
            ],
        ]);

        $this->assertTrue(self::$kernel->getContainer()->has(ResponsiveAttributeGenerator::class));
    }

    public function testExplicitResponsiveStrategyGeneratorWinsOverTheVariantPipeline(): void
    {
        self::bootKernel([
            'progressive_image' => [
                'resolvers' => [
                    'default' => ['type' => 'filesystem', 'roots' => [__DIR__.'/../Fixtures/images']],
                ],
                'responsive_strategy' => [
                    'generator' => 'test.custom_generator',
                ],
                'variant_store' => [
                    'storage' => 'test.variant_storage',
                ],
                'generation' => [
                    'strategy' => 'sync',
                ],
            ],
        ], function (ContainerBuilder $container): void {
            $container->register('test.custom_generator', FakeDimensionsEchoingUrlGenerator::class)
                ->setPublic(true);
            $container->register('test.variant_storage.adapter', LocalFilesystemAdapter::class)
                ->setArgument('$location', sys_get_temp_dir());
            $container->register('test.variant_storage', Filesystem::class)
                ->setArgument('$adapter', new Reference('test.variant_storage.adapter'))
                ->setPublic(true);
        });

        $generator = self::$kernel->getContainer()->get(ResponsiveImageUrlGeneratorInterface::class);

        $this->assertInstanceOf(FakeDimensionsEchoingUrlGenerator::class, $generator);
        $this->assertNotInstanceOf(VariantResponsiveImageUrlGenerator::class, $generator);
    }

    public function testVariantPipelineWinsOverTheDefaultGeneratorWhenNoCustomGeneratorIsConfigured(): void
    {
        self::bootKernel([
            'progressive_image' => [
                'resolvers' => [
                    'default' => ['type' => 'filesystem', 'roots' => [__DIR__.'/../Fixtures/images']],
                ],
                'variant_store' => [
                    'storage' => 'test.variant_storage',
                ],
                'generation' => [
                    'strategy' => 'sync',
                ],
            ],
        ], function (ContainerBuilder $container): void {
            $container->register('test.variant_storage.adapter', LocalFilesystemAdapter::class)
                ->setArgument('$location', sys_get_temp_dir());
            $container->register('test.variant_storage', Filesystem::class)
                ->setArgument('$adapter', new Reference('test.variant_storage.adapter'))
                ->setPublic(true);
        });

        $generator = self::$kernel->getContainer()->get(ResponsiveImageUrlGeneratorInterface::class);

        $this->assertInstanceOf(VariantResponsiveImageUrlGenerator::class, $generator);
    }

    public function testAmbiguousDefaultResolverWithMultipleNamedResolversFailsFast(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/resolver/i');

        self::bootKernel([
            'progressive_image' => [
                'resolvers' => [
                    'uploads' => ['type' => 'filesystem', 'roots' => ['/tmp/uploads']],
                    'cdn_cache' => ['type' => 'filesystem', 'roots' => ['/tmp/cdn']],
                ],
            ],
        ]);
    }

    public function testNamedFilesystemResolverDefaultsAllowUnresolvableToFalse(): void
    {
        // Configuration.php declares ->booleanNode('allowUnresolvable')->defaultFalse(), so
        // an omitted value must resolve to false, not the "?? true" fallback previously in
        // ProgressiveImageExtension.php (which could never actually run, since
        // processConfiguration() always fills this key in).
        self::bootKernel([
            'progressive_image' => [
                'resolvers' => [
                    'default' => ['type' => 'filesystem', 'roots' => ['/tmp/pgi-does-not-exist-'.uniqid()]],
                ],
            ],
        ]);

        $this->expectException(\InvalidArgumentException::class);

        self::getContainer()->get('progressive_image.resolver.default');
    }
}
