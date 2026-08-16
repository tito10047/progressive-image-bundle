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

namespace Tito10047\ProgressiveImageBundle\Tests\Functional\Twig;

use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Tito10047\ProgressiveImageBundle\Tests\Integration\PGITestCase;

/**
 * Renders the pgi_filter() Twig function through a real compiled container + real Twig
 * environment — proves the DI wiring produces a working end-to-end URL, not just that the
 * individual classes behave correctly in isolation (already covered by
 * FilterUrlExtensionTest and ResolveFilterUrlHandlerTest).
 */
final class FilterUrlFunctionTest extends PGITestCase
{
    private string $storageRoot;

    protected function tearDown(): void
    {
        if (isset($this->storageRoot) && is_dir($this->storageRoot)) {
            $this->removeDirectory($this->storageRoot);
        }
    }

    public function testPgiFilterReturnsAGeneratedVariantUrlOnASynchronousHit(): void
    {
        $this->storageRoot = sys_get_temp_dir().'/pgi-filter-fn-'.bin2hex(random_bytes(8));
        mkdir($this->storageRoot);
        $storageRoot = $this->storageRoot;

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
                'filter_sets' => [
                    'thumb_small' => [
                        'filters' => ['thumbnail' => ['size' => [40, 40], 'mode' => 'outbound']],
                    ],
                ],
            ],
        ], function (ContainerBuilder $container) use ($storageRoot): void {
            $container->register('test.variant_storage.adapter', LocalFilesystemAdapter::class)
                ->setArgument('$location', $storageRoot);
            $container->register('test.variant_storage', Filesystem::class)
                ->setArgument('$adapter', new Reference('test.variant_storage.adapter'))
                ->setPublic(true);
        });

        $twig = self::getContainer()->get('twig');
        $html = $twig->createTemplate("{{ pgi_filter('test.png', 'thumb_small') }}")->render();

        self::assertStringStartsWith('/media/pgi/', $html);

        $files = glob($this->storageRoot.'/*/*/*/*.jpg') ?: [];
        self::assertNotEmpty($files, 'sync generation must have written a variant to storage');
    }

    public function testPgiFilterFallsBackToTheOriginalUrlWhileGenerationIsPending(): void
    {
        $this->storageRoot = sys_get_temp_dir().'/pgi-filter-fn-'.bin2hex(random_bytes(8));
        mkdir($this->storageRoot);
        $storageRoot = $this->storageRoot;

        self::bootKernel([
            'progressive_image' => [
                'resolvers' => [
                    'default' => ['type' => 'filesystem', 'roots' => [__DIR__.'/../Fixtures/images']],
                ],
                'variant_store' => [
                    'storage' => 'test.variant_storage',
                ],
                'generation' => [
                    'strategy' => 'terminate',
                ],
                'filter_sets' => [
                    'thumb_small' => [
                        'filters' => ['thumbnail' => ['size' => [40, 40], 'mode' => 'outbound']],
                    ],
                ],
            ],
        ], function (ContainerBuilder $container) use ($storageRoot): void {
            $container->register('test.variant_storage.adapter', LocalFilesystemAdapter::class)
                ->setArgument('$location', $storageRoot);
            $container->register('test.variant_storage', Filesystem::class)
                ->setArgument('$adapter', new Reference('test.variant_storage.adapter'))
                ->setPublic(true);
        });

        $twig = self::getContainer()->get('twig');
        $html = $twig->createTemplate("{{ pgi_filter('test.png', 'thumb_small') }}")->render();

        self::assertStringEndsWith('/test.png', $html);
        self::assertStringNotContainsString('/media/pgi/', $html);
    }

    private function removeDirectory(string $dir): void
    {
        $entries = scandir($dir);
        if (false === $entries) {
            return;
        }

        foreach ($entries as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }

            $path = $dir.'/'.$entry;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
