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

namespace Tito10047\ProgressiveImageBundle\Tests\Functional\Variant;

use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpFoundation\Request;
use Tito10047\ProgressiveImageBundle\Tests\Integration\ProgressiveImageTestingKernel;

/**
 * Full HTTP-kernel round trip for the on-the-fly resolve route: routing, the
 * {filterSet}/{path} parameters, and the controller's synchronous-generate-then-redirect
 * behavior, all through the real compiled container — the unit-level
 * ResolveFilterControllerTest already covers the controller's own logic against fakes, this
 * proves the route is actually reachable and wired correctly end to end.
 */
final class ResolveFilterRouteTest extends TestCase
{
    private string $storageRoot;

    protected function tearDown(): void
    {
        if (isset($this->storageRoot) && is_dir($this->storageRoot)) {
            $this->removeDirectory($this->storageRoot);
        }
    }

    private function bootKernelWithFlysystemStorage(array $progressiveImageConfig): ProgressiveImageTestingKernel
    {
        $this->storageRoot = sys_get_temp_dir().'/pgi-resolve-route-'.bin2hex(random_bytes(8));
        mkdir($this->storageRoot);
        $storageRoot = $this->storageRoot;

        $kernel = new ProgressiveImageTestingKernel([
            'progressive_image' => $progressiveImageConfig,
        ]);
        $kernel->setCustomConfiguration(function (ContainerBuilder $container) use ($storageRoot): void {
            $container->register('test.variant_storage.adapter', LocalFilesystemAdapter::class)
                ->setArgument('$location', $storageRoot);
            $container->register('test.variant_storage', Filesystem::class)
                ->setArgument('$adapter', new Reference('test.variant_storage.adapter'))
                ->setPublic(true);
        });
        $kernel->boot();

        return $kernel;
    }

    public function testFirstRequestGeneratesSynchronouslyAndRedirectsToTheVariant(): void
    {
        $kernel = $this->bootKernelWithFlysystemStorage([
            'resolvers' => [
                'default' => ['type' => 'filesystem', 'roots' => [__DIR__.'/../Fixtures/images']],
            ],
            'variant_store' => [
                'storage' => 'test.variant_storage',
            ],
            'filter_sets' => [
                'thumb_small' => [
                    'filters' => ['thumbnail' => ['size' => [40, 40], 'mode' => 'outbound']],
                ],
            ],
        ]);

        $response = $kernel->handle(Request::create('/media/pgi/resolve/thumb_small/test.png'));

        self::assertSame(302, $response->getStatusCode());
        self::assertStringStartsWith('/media/pgi/', (string) $response->headers->get('Location'));

        $files = glob($this->storageRoot.'/*/*/*/*.jpg') ?: [];
        self::assertNotEmpty($files, 'the first request must have generated the variant synchronously');

        $kernel->shutdown();
    }

    public function testSecondRequestForTheSameVariantIsAStorageHitWithoutRegenerating(): void
    {
        $kernel = $this->bootKernelWithFlysystemStorage([
            'resolvers' => [
                'default' => ['type' => 'filesystem', 'roots' => [__DIR__.'/../Fixtures/images']],
            ],
            'variant_store' => [
                'storage' => 'test.variant_storage',
            ],
            'filter_sets' => [
                'thumb_small' => [
                    'filters' => ['thumbnail' => ['size' => [40, 40], 'mode' => 'outbound']],
                ],
            ],
        ]);

        $first = $kernel->handle(Request::create('/media/pgi/resolve/thumb_small/test.png'));
        $filesAfterFirst = glob($this->storageRoot.'/*/*/*/*.jpg') ?: [];

        $second = $kernel->handle(Request::create('/media/pgi/resolve/thumb_small/test.png'));
        $filesAfterSecond = glob($this->storageRoot.'/*/*/*/*.jpg') ?: [];

        self::assertSame($first->headers->get('Location'), $second->headers->get('Location'));
        self::assertCount(\count($filesAfterFirst), $filesAfterSecond, 'the second request must not write a new variant file');

        $kernel->shutdown();
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
