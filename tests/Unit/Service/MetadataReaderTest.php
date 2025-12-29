<?php

namespace Tito10047\ProgressiveImageBundle\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
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
            ->with(md5($src))
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
            ->with($src, [])
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
            ->with($src, [])
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
}
