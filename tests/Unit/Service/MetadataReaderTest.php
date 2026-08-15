<?php

/*
 * This file is part of the Progressive Image Bundle.
 *
 * (c) Jozef Môstka <https://github.com/tito10047/progressive-image-bundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tito10047\ProgressiveImageBundle\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Tito10047\ProgressiveImageBundle\Analyzer\ImageAnalyzerInterface;
use Tito10047\ProgressiveImageBundle\DTO\ImageMetadata;
use Tito10047\ProgressiveImageBundle\Event\ImageNotFoundEvent;
use Tito10047\ProgressiveImageBundle\Exception\PathResolutionException;
use Tito10047\ProgressiveImageBundle\Loader\LoaderInterface;
use Tito10047\ProgressiveImageBundle\Resolver\PathResolverInterface;
use Tito10047\ProgressiveImageBundle\Service\MetadataReader;

class MetadataReaderTest extends TestCase
{
    private $dispatcher;
    private $cache;
    private $analyzer;
    private $loader;
    private $pathResolver;

    protected function setUp(): void
    {
        $this->dispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->cache = $this->createMock(CacheInterface::class);
        $this->analyzer = $this->createMock(ImageAnalyzerInterface::class);
        $this->loader = $this->createMock(LoaderInterface::class);
        $this->pathResolver = $this->createMock(PathResolverInterface::class);
    }

    public function testGetMetadataReturnsCachedValue(): void
    {
        $src = 'test.jpg';
        $metadata = new ImageMetadata('hash', 100, 100);

        $this->cache->expects($this->once())
            ->method('get')
            ->with('pgi_meta_'.md5($src))
            ->willReturn($metadata);

        $reader = new MetadataReader(
            $this->dispatcher,
            $this->cache,
            $this->analyzer,
            $this->loader,
            $this->pathResolver,
            3600,
            null
        );

        $result = $reader->getMetadata($src);
        $this->assertSame($metadata, $result);
    }

    public function testGetMetadataCalculatesAndCachesValue(): void
    {
        $src = 'test.jpg';
        $path = '/absolute/path/test.jpg';
        $metadata = new ImageMetadata('hash', 100, 100);

        $this->cache->expects($this->once())
            ->method('get')
            ->willReturnCallback(function ($key, $callback) {
                $item = $this->createMock(\Symfony\Contracts\Cache\ItemInterface::class);

                return $callback($item);
            });

        $this->pathResolver->expects($this->once())
            ->method('resolve')
            ->with($src)
            ->willReturn($path);

        $this->analyzer->expects($this->once())
            ->method('analyze')
            ->with($this->loader, $path)
            ->willReturn($metadata);

        $reader = new MetadataReader(
            $this->dispatcher,
            $this->cache,
            $this->analyzer,
            $this->loader,
            $this->pathResolver,
            3600,
            null
        );

        $result = $reader->getMetadata($src);
        $this->assertSame($metadata, $result);
    }

    public function testGetMetadataDispatchesEventOnFailure(): void
    {
        $src = 'not-found.jpg';

        $this->cache->expects($this->once())
            ->method('get')
            ->willReturnCallback(function ($key, $callback) {
                $item = $this->createMock(\Symfony\Contracts\Cache\ItemInterface::class);

                return $callback($item);
            });

        $this->pathResolver->expects($this->once())
            ->method('resolve')
            ->with($src)
            ->willThrowException(new PathResolutionException());

        $this->dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(ImageNotFoundEvent::class), ImageNotFoundEvent::NAME);

        $reader = new MetadataReader(
            $this->dispatcher,
            $this->cache,
            $this->analyzer,
            $this->loader,
            $this->pathResolver,
            3600,
            null
        );

        $this->expectException(PathResolutionException::class);
        $reader->getMetadata($src);
    }

    public function testFallbackMetadataIsComputedOnceAndSharedAcrossDifferentBrokenSources(): void
    {
        $fallbackPath = 'fallback.jpg';
        $resolvedFallbackPath = '/absolute/path/fallback.jpg';
        $metadata = new ImageMetadata('hash', 1, 1);

        $this->pathResolver->method('resolve')->willReturnCallback(
            function (string $path) use ($fallbackPath, $resolvedFallbackPath) {
                if ($fallbackPath === $path) {
                    return $resolvedFallbackPath;
                }

                throw new PathResolutionException();
            }
        );

        // The expensive analysis of the shared fallback image must only run once, even
        // though it's reached via two different unresolvable original sources.
        $this->analyzer->expects($this->once())
            ->method('analyze')
            ->with($this->loader, $resolvedFallbackPath)
            ->willReturn($metadata);

        $reader = new MetadataReader(
            $this->dispatcher,
            new ArrayAdapter(),
            $this->analyzer,
            $this->loader,
            $this->pathResolver,
            3600,
            $fallbackPath
        );

        $result1 = $reader->getMetadata('broken-one.jpg');
        $result2 = $reader->getMetadata('broken-two.jpg');

        // ArrayAdapter deep-clones cached objects on read, so the two results are equal
        // (same underlying analysis) but not the same instance.
        $this->assertEquals($metadata, $result1);
        $this->assertEquals($metadata, $result2);
    }

    public function testUnresolvableSourceWithoutFallbackIsNegativelyCachedAndDoesNotRetryResolution(): void
    {
        $src = 'permanently-missing.jpg';

        // Resolution (and the resulting event dispatch) must only happen once: the second
        // request for the same permanently-broken source should be served from a cached
        // negative result instead of retrying resolve().
        $this->pathResolver->expects($this->once())
            ->method('resolve')
            ->with($src)
            ->willThrowException(new PathResolutionException());

        $this->dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(ImageNotFoundEvent::class), ImageNotFoundEvent::NAME);

        $reader = new MetadataReader(
            $this->dispatcher,
            new ArrayAdapter(),
            $this->analyzer,
            $this->loader,
            $this->pathResolver,
            3600,
            null
        );

        try {
            $reader->getMetadata($src);
            $this->fail('Expected a PathResolutionException.');
        } catch (PathResolutionException) {
        }

        $this->expectException(PathResolutionException::class);
        $reader->getMetadata($src);
    }
}
