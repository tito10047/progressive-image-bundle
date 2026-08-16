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

namespace Tito10047\ProgressiveImageBundle\Tests\Integration\DependencyInjection;

use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Process\ExecutableFinder;
use Tito10047\ProgressiveImageBundle\Tests\Integration\ProgressiveImageTestingKernel;
use Tito10047\ProgressiveImageBundle\Variant\Application\Handler\ResolveVariantUrlHandler;
use Tito10047\ProgressiveImageBundle\Variant\Application\Query\ResolveVariantUrl;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\SourcePath;

/**
 * The most valuable test for hand-wired DI code is a real container boot — unit-testing
 * the wiring method call-by-call wouldn't catch a broken service graph. This boots the
 * bundle with the Variant context fully configured (sync strategy, local Flysystem
 * storage) and exercises the real end-to-end resolve -> generate -> resolve-again flow
 * against the existing test.png fixture.
 */
final class VariantContextWiringTest extends TestCase
{
    private string $storageRoot;

    protected function tearDown(): void
    {
        if (isset($this->storageRoot) && is_dir($this->storageRoot)) {
            $this->removeDirectory($this->storageRoot);
        }
    }

    public function testFullVariantPipelineResolvesFromTheCompiledContainer(): void
    {
        $this->storageRoot = sys_get_temp_dir().'/pgi-wiring-'.bin2hex(random_bytes(8));
        mkdir($this->storageRoot);

        $kernel = new ProgressiveImageTestingKernel([
            'progressive_image' => [
                'resolvers' => [
                    'default' => ['type' => 'filesystem', 'roots' => [__DIR__.'/../../Functional/Fixtures/images']],
                ],
                'variant_store' => [
                    'storage' => 'test.variant_storage',
                ],
                'generation' => [
                    'strategy' => 'sync',
                ],
                'filter_sets' => [
                    'thumbnail_square' => [
                        'filters' => ['thumbnail' => ['size' => [50, 50], 'mode' => 'outbound']],
                    ],
                ],
            ],
        ]);

        $storageRoot = $this->storageRoot;
        $kernel->setCustomConfiguration(function (ContainerBuilder $container) use ($storageRoot): void {
            $container->register('test.variant_storage.adapter', LocalFilesystemAdapter::class)
                ->setArgument('$location', $storageRoot);
            $container->register('test.variant_storage', Filesystem::class)
                ->setArgument('$adapter', new Reference('test.variant_storage.adapter'))
                ->setPublic(true);
        });

        $kernel->boot();
        $container = $kernel->getContainer();

        $resolveHandler = $container->get(ResolveVariantUrlHandler::class);
        self::assertInstanceOf(ResolveVariantUrlHandler::class, $resolveHandler);

        $query = new ResolveVariantUrl(new SourcePath('test.png'), 50, 50, 'thumbnail_square');

        // generation.strategy=sync runs generation inline: by the time dispatch() returns
        // inside resolve(), the variant is already in storage, so even the very first
        // resolve() call must report a hit, not the "original image" fallback.
        $firstResolve = $resolveHandler($query);
        self::assertFalse($firstResolve->pending, 'sync generation already completed within this same resolve() call');
        self::assertStringStartsWith('/media/pgi/', $firstResolve->url);

        // ResolveVariantUrlHandler is shared(false), so fetching it again yields a fresh
        // instance — the same as a second, independent injection point — confirming the
        // hit isn't an artifact of the first handler's own memoization.
        $secondResolveHandler = $container->get(ResolveVariantUrlHandler::class);
        $hit = $secondResolveHandler($query);
        self::assertFalse($hit->pending, 'second resolve, against a fresh handler instance, must be a storage hit');
        self::assertStringStartsWith('/media/pgi/', $hit->url);

        $kernel->shutdown();
    }

    public function testEnabledPostProcessorRunsDuringGeneration(): void
    {
        $bin = (new ExecutableFinder())->find('jpegoptim');
        if (null === $bin) {
            self::markTestSkipped('jpegoptim binary is not installed.');
        }

        $this->storageRoot = sys_get_temp_dir().'/pgi-wiring-'.bin2hex(random_bytes(8));
        mkdir($this->storageRoot);

        $kernel = new ProgressiveImageTestingKernel([
            'progressive_image' => [
                'resolvers' => [
                    'default' => ['type' => 'filesystem', 'roots' => [__DIR__.'/../../Functional/Fixtures/images']],
                ],
                'variant_store' => [
                    'storage' => 'test.variant_storage',
                ],
                'generation' => [
                    'strategy' => 'sync',
                ],
                'formats' => [
                    'default' => 'jpeg',
                ],
                'post_processors' => [
                    'jpegoptim' => ['enabled' => true, 'bin' => $bin],
                ],
            ],
        ]);

        $storageRoot = $this->storageRoot;
        $kernel->setCustomConfiguration(function (ContainerBuilder $container) use ($storageRoot): void {
            $container->register('test.variant_storage.adapter', LocalFilesystemAdapter::class)
                ->setArgument('$location', $storageRoot);
            $container->register('test.variant_storage', Filesystem::class)
                ->setArgument('$adapter', new Reference('test.variant_storage.adapter'))
                ->setPublic(true);
        });

        $kernel->boot();
        $container = $kernel->getContainer();

        $resolveHandler = $container->get(ResolveVariantUrlHandler::class);
        $resolveHandler(new ResolveVariantUrl(new SourcePath('test.png'), 40, 40));

        $files = glob($this->storageRoot.'/jpeg/*/*/*.jpg');
        self::assertNotFalse($files);
        self::assertCount(1, $files, 'sync generation must have written exactly one jpeg variant');

        $info = getimagesize($files[0]);
        self::assertIsArray($info, 'the post-processed bytes must still be a valid, decodable JPEG');
        self::assertSame('image/jpeg', $info['mime']);

        $kernel->shutdown();
    }

    /**
     * Full round trip for the "wait" fallback: resolve produces a signed URL to
     * pgi_variant_serve, and dispatching that exact URL through the real HTTP kernel
     * (routing + #[MapQueryParameter] argument resolution + the controller) generates the
     * variant and redirects to its public path.
     */
    public function testWaitFallbackUrlIsServableByTheRealHttpKernel(): void
    {
        $this->storageRoot = sys_get_temp_dir().'/pgi-wiring-'.bin2hex(random_bytes(8));
        mkdir($this->storageRoot);

        $kernel = new ProgressiveImageTestingKernel([
            'progressive_image' => [
                'resolvers' => [
                    'default' => ['type' => 'filesystem', 'roots' => [__DIR__.'/../../Functional/Fixtures/images']],
                ],
                'variant_store' => [
                    'storage' => 'test.variant_storage',
                ],
                'generation' => [
                    'strategy' => 'terminate',
                    'fallback_while_pending' => 'wait',
                ],
            ],
        ]);

        $storageRoot = $this->storageRoot;
        $kernel->setCustomConfiguration(function (ContainerBuilder $container) use ($storageRoot): void {
            $container->register('test.variant_storage.adapter', LocalFilesystemAdapter::class)
                ->setArgument('$location', $storageRoot);
            $container->register('test.variant_storage', Filesystem::class)
                ->setArgument('$adapter', new Reference('test.variant_storage.adapter'))
                ->setPublic(true);
        });

        $kernel->boot();
        $container = $kernel->getContainer();

        $resolveHandler = $container->get(ResolveVariantUrlHandler::class);
        self::assertInstanceOf(ResolveVariantUrlHandler::class, $resolveHandler);

        $resolved = $resolveHandler(new ResolveVariantUrl(new SourcePath('test.png'), 40, 40));
        self::assertTrue($resolved->pending);
        self::assertStringContainsString('/media/pgi/wait', $resolved->url);

        $request = Request::create($resolved->url);
        $response = $kernel->handle($request);

        self::assertSame(302, $response->getStatusCode());
        self::assertStringStartsWith('/media/pgi/', (string) $response->headers->get('Location'));

        $kernel->shutdown();
    }

    /**
     * TerminateGenerationDispatcher is both an event listener (kernel.terminate ->
     * onTerminate()) AND injected via the GenerationDispatcher alias into
     * ResolveVariantUrlHandler — if it were wired as non-shared, those would resolve to two
     * different instances, and onTerminate() would flush an empty queue since dispatch()
     * was called on a different object. This proves the real end-to-end flush still works
     * with whatever sharedness the DI wiring actually uses.
     */
    public function testTerminateStrategyFlushesQueuedGenerationsOnKernelTerminate(): void
    {
        $this->storageRoot = sys_get_temp_dir().'/pgi-wiring-'.bin2hex(random_bytes(8));
        mkdir($this->storageRoot);

        $kernel = new ProgressiveImageTestingKernel([
            'progressive_image' => [
                'resolvers' => [
                    'default' => ['type' => 'filesystem', 'roots' => [__DIR__.'/../../Functional/Fixtures/images']],
                ],
                'variant_store' => [
                    'storage' => 'test.variant_storage',
                ],
                'generation' => [
                    'strategy' => 'terminate',
                ],
            ],
        ]);

        $storageRoot = $this->storageRoot;
        $kernel->setCustomConfiguration(function (ContainerBuilder $container) use ($storageRoot): void {
            $container->register('test.variant_storage.adapter', LocalFilesystemAdapter::class)
                ->setArgument('$location', $storageRoot);
            $container->register('test.variant_storage', Filesystem::class)
                ->setArgument('$adapter', new Reference('test.variant_storage.adapter'))
                ->setPublic(true);
        });

        $kernel->boot();
        $container = $kernel->getContainer();

        $resolveHandler = $container->get(ResolveVariantUrlHandler::class);
        self::assertInstanceOf(ResolveVariantUrlHandler::class, $resolveHandler);

        $resolved = $resolveHandler(new ResolveVariantUrl(new SourcePath('test.png'), 40, 40));
        self::assertTrue($resolved->pending, 'generation must not have run yet — it is queued for kernel.terminate');

        $request = Request::create('/');
        $response = new \Symfony\Component\HttpFoundation\Response();
        $kernel->terminate($request, $response);

        $files = glob($this->storageRoot.'/jpeg/*/*/*.jpg') ?: [];
        self::assertNotEmpty($files, 'kernel.terminate must have flushed the queued generation onto disk');

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
