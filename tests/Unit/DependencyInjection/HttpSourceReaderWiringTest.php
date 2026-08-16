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
use Tito10047\ProgressiveImageBundle\Variant\Domain\Port\SourceReader;
use Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Source\ChainSourceReader;
use Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Source\HttpSourceReader;
use Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Source\ResolverChainSourceReader;

/**
 * Exercises ProgressiveImageExtension::load() directly against a bare ContainerBuilder —
 * no kernel boot/compile needed, since nothing here reads a compiled/dumped service graph,
 * only Definitions and aliases as load() leaves them.
 */
final class HttpSourceReaderWiringTest extends TestCase
{
    /**
     * @param array<string, mixed> $progressiveImageConfig
     */
    private function buildContainer(array $progressiveImageConfig): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['TwigBundle' => true]);
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());
        $container->setParameter('kernel.cache_dir', sys_get_temp_dir());
        $container->setParameter('kernel.secret', 'test-secret');

        (new ProgressiveImageExtension())->load(['progressive_image' => $progressiveImageConfig], $container);

        return $container;
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function baseConfig(array $overrides = []): array
    {
        return array_replace_recursive([
            'resolvers' => [
                'default' => ['type' => 'filesystem', 'roots' => ['/tmp']],
            ],
            'variant_store' => [
                'storage' => 'test.variant_storage',
            ],
        ], $overrides);
    }

    public function testSourceReaderAliasIsResolverChainSourceReaderByDefault(): void
    {
        $container = $this->buildContainer($this->baseConfig());

        self::assertSame(ResolverChainSourceReader::class, (string) $container->getAlias(SourceReader::class));
    }

    public function testHttpSourceLoadingIsNotWiredWhenDisabled(): void
    {
        $container = $this->buildContainer($this->baseConfig());

        self::assertFalse($container->hasDefinition(HttpSourceReader::class));
        self::assertFalse($container->hasDefinition(ChainSourceReader::class));
    }

    public function testSourceReaderAliasBecomesChainSourceReaderWhenHttpLoadingIsEnabled(): void
    {
        $container = $this->buildContainer($this->baseConfig([
            'variant_source' => [
                'http' => [
                    'enabled' => true,
                    'allowed_hosts' => ['images.example.com'],
                ],
            ],
        ]));

        self::assertSame(ChainSourceReader::class, (string) $container->getAlias(SourceReader::class));
        self::assertTrue($container->hasDefinition(HttpSourceReader::class));

        $definition = $container->getDefinition(HttpSourceReader::class);
        self::assertSame(['images.example.com'], $definition->getArgument('$allowedHosts'));
        self::assertSame(5, $definition->getArgument('$timeoutSeconds'));
    }

    public function testHttpSourceLoadingTimeoutIsConfigurable(): void
    {
        $container = $this->buildContainer($this->baseConfig([
            'variant_source' => [
                'http' => [
                    'enabled' => true,
                    'allowed_hosts' => ['images.example.com'],
                    'timeout' => 15,
                ],
            ],
        ]));

        $definition = $container->getDefinition(HttpSourceReader::class);
        self::assertSame(15, $definition->getArgument('$timeoutSeconds'));
    }
}
