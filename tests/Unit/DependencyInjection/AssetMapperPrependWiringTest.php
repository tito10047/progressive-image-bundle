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
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tito10047\ProgressiveImageBundle\DependencyInjection\ProgressiveImageExtension;

final class AssetMapperPrependWiringTest extends TestCase
{
    private function buildContainer(bool $frameworkBundleRegistered): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', []);
        $container->setParameter(
            'kernel.bundles_metadata',
            $frameworkBundleRegistered
                ? ['FrameworkBundle' => ['path' => \dirname((new \ReflectionClass(FrameworkBundle::class))->getFileName())]]
                : []
        );

        return $container;
    }

    public function testRegistersItsAssetsUnderTheNpmStyleScopedNamespaceMatchingPackageJson(): void
    {
        // assets/package.json declares "name": "@tito10047/progressive-image-bundle" — the
        // asset_mapper namespace must match exactly, or AssetMapper's autoimport (which
        // resolves "@tito10047/progressive-image-bundle/styles/style.css" against the
        // registered namespace) silently never fires.
        $container = $this->buildContainer(frameworkBundleRegistered: true);

        (new ProgressiveImageExtension())->prepend($container);

        $paths = [];
        foreach ($container->getExtensionConfig('framework') as $config) {
            $paths += $config['asset_mapper']['paths'] ?? [];
        }

        self::assertSame(['@tito10047/progressive-image-bundle'], array_values($paths));
    }

    public function testDoesNotRegisterAnAssetMapperPathWhenFrameworkBundleAssetMapperIsUnavailable(): void
    {
        // Registering framework.asset_mapper.paths when the asset-mapper component isn't
        // installed would be dead config at best; guard it the same way altcha-bundle does.
        $container = $this->buildContainer(frameworkBundleRegistered: false);

        (new ProgressiveImageExtension())->prepend($container);

        $frameworkConfig = $container->getExtensionConfig('framework');
        foreach ($frameworkConfig as $config) {
            self::assertArrayNotHasKey('asset_mapper', $config);
        }
    }
}
