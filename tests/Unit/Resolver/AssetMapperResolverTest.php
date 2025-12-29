<?php

namespace Tito10047\ProgressiveImageBundle\Tests\Unit\Resolver;

use PHPUnit\Framework\TestCase;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\AssetMapper\MappedAsset;
use Tito10047\ProgressiveImageBundle\Exception\PathResolutionException;
use Tito10047\ProgressiveImageBundle\Resolver\AssetMapperResolver;

class AssetMapperResolverTest extends TestCase
{
    public function testResolveFoundAsset(): void
    {
        $assetMapper = $this->createMock(AssetMapperInterface::class);
        $asset = new MappedAsset(
            logicalPath: 'assets/test.jpg',
            sourcePath: '/absolute/path/to/test.jpg',
            publicPathWithoutDigest: '/assets/test.jpg',
            publicPath: '/assets/test.jpg'
        );

        $assetMapper->expects($this->once())
            ->method('allAssets')
            ->willReturn([$asset]);

        $resolver = new AssetMapperResolver($assetMapper);
        $result = $resolver->resolve('assets/test.jpg');

        $this->assertSame('/absolute/path/to/test.jpg', $result);
    }

    public function testResolveNotFoundThrowsException(): void
    {
        $assetMapper = $this->createMock(AssetMapperInterface::class);
        $assetMapper->expects($this->once())
            ->method('allAssets')
            ->willReturn([]);

        $resolver = new AssetMapperResolver($assetMapper);

        $this->expectException(PathResolutionException::class);
        $resolver->resolve('non-existent.jpg');
    }
}
