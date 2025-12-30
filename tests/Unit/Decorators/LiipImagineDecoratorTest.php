<?php

namespace Tito10047\ProgressiveImageBundle\Tests\Unit\Decorators;

use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Liip\ImagineBundle\Imagine\Filter\FilterConfiguration;
use PHPUnit\Framework\TestCase;
use Tito10047\ProgressiveImageBundle\Decorators\LiipImagineDecorator;

class LiipImagineDecoratorTest extends TestCase
{
    public function testResolve(): void
    {
        $cacheManager = $this->createMock(CacheManager::class);
        $filterConfig = $this->createMock(FilterConfiguration::class);
        $path = 'images/test.jpg';
        $filter = 'my_thumb';
        $expectedUrl = 'http://localhost/media/cache/resolve/my_thumb/images/test.jpg';

        $cacheManager->expects($this->once())
            ->method('getBrowserPath')
            ->with($path, $filter)
            ->willReturn($expectedUrl);

        $decorator = new LiipImagineDecorator($cacheManager, $filterConfig);
        $result = $decorator->decorate($path, ['filter' => $filter]);

        $this->assertSame($expectedUrl, $result);
    }

}
