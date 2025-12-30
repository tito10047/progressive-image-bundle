<?php

namespace Tito10047\ProgressiveImageBundle\Tests\Functional;

use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Symfony\UX\TwigComponent\Test\InteractsWithTwigComponents;
use Tito10047\ProgressiveImageBundle\Tests\Integration\PGITestCase;
use Tito10047\ProgressiveImageBundle\Twig\Components\Image;

class LiipImagineDecoratorTest extends PGITestCase
{
    use InteractsWithTwigComponents;

    public function testImageComponentWithLiipImagineDecorator(): void
    {
        $cacheManager = $this->createMock(CacheManager::class);
        $cacheManager->expects($this->once())
            ->method('getBrowserPath')
            ->with('images/test.jpg', 'my_filter')
            ->willReturn('http://localhost/media/cache/resolve/my_filter/images/test.jpg');

        self::bootKernel([
            "progressive_image"=>[
				'path_decorators' => [
					'progressive_image.decorator.liip_imagine'
				]
			]
        ]);

        // Manuálne nahradíme službu mockom, keďže testujeme integráciu dekorátora
        self::getContainer()->set('liip_imagine.cache.manager', $cacheManager);

        $component = $this->mountTwigComponent(
            name: 'pgi:Image',
            data: [
                'src' => 'images/test.jpg',
                'context' => [
                    'filter' => 'my_filter'
                ]
            ]
        );

        $this->assertInstanceOf(Image::class, $component);
        $this->assertSame('http://localhost/media/cache/resolve/my_filter/images/test.jpg', $component->getDecoratedSrc());
    }
}
