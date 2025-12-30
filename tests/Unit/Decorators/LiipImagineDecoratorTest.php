<?php

namespace Tito10047\ProgressiveImageBundle\Tests\Unit\Decorators;

use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use PHPUnit\Framework\TestCase;
use Tito10047\ProgressiveImageBundle\Decorators\LiipImagineDecorator;

class LiipImagineDecoratorTest extends TestCase
{
    public function testResolve(): void
    {
        $cacheManager = $this->createMock(CacheManager::class);
        $path = 'images/test.jpg';
        $filter = 'my_thumb';
        $expectedUrl = 'http://localhost/media/cache/resolve/my_thumb/images/test.jpg';

        $cacheManager->expects($this->once())
            ->method('getBrowserPath')
            ->with($path, $filter)
            ->willReturn($expectedUrl);

        $decorator = new LiipImagineDecorator($cacheManager);
        $result = $decorator->decorate($path, ['filter' => $filter]);

        $this->assertSame($expectedUrl, $result);
    }

    public function testResolveThrowsExceptionIfFilterIsMissing(): void
    {
        $cacheManager = $this->createMock(CacheManager::class);
        $decorator = new LiipImagineDecorator($cacheManager);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('filter is required');

        $decorator->decorate('test.jpg', []);
    }
}
