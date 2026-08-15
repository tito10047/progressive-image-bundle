<?php

/*
 * This file is part of the Progressive Image Bundle.
 *
 * (c) Jozef Môstka <https://github.com/tito10047/progressive-image-bundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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

    public function testResolveBuildsThePublicPathIndexOnlyOnceAcrossMultipleCalls(): void
    {
        $assetMapper = $this->createMock(AssetMapperInterface::class);
        $assetOne = new MappedAsset(
            logicalPath: 'assets/one.jpg',
            sourcePath: '/absolute/path/to/one.jpg',
            publicPathWithoutDigest: '/assets/one.jpg',
            publicPath: '/assets/one.jpg'
        );
        $assetTwo = new MappedAsset(
            logicalPath: 'assets/two.jpg',
            sourcePath: '/absolute/path/to/two.jpg',
            publicPathWithoutDigest: '/assets/two.jpg',
            publicPath: '/assets/two.jpg'
        );

        // allAssets() does an O(n) scan of the whole asset manifest — it must only be
        // called once, not once per resolve() call.
        $assetMapper->expects($this->once())
            ->method('allAssets')
            ->willReturn([$assetOne, $assetTwo]);

        $resolver = new AssetMapperResolver($assetMapper);

        $this->assertSame('/absolute/path/to/one.jpg', $resolver->resolve('assets/one.jpg'));
        $this->assertSame('/absolute/path/to/two.jpg', $resolver->resolve('assets/two.jpg'));
    }
}
