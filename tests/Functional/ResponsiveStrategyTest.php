<?php

namespace Tito10047\ProgressiveImageBundle\Tests\Functional;

use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Symfony\UX\TwigComponent\Test\InteractsWithTwigComponents;
use Tito10047\ProgressiveImageBundle\Tests\Integration\PGITestCase;
use Tito10047\ProgressiveImageBundle\Twig\Components\Image;

class ResponsiveStrategyTest extends PGITestCase
{
    use InteractsWithTwigComponents;

    public function testResponsiveImageComponent(): void
    {
        if (!class_exists(CacheManager::class)) {
            $this->markTestSkipped('LiipImagineBundle is not installed.');
        }
        $cacheManager = $this->createMock(CacheManager::class);
        $cacheManager->method('getBrowserPath')
            ->willReturnCallback(function($path, $filter) {
                return "/cache/$filter/$path";
            });

        $filterConfig = $this->createMock(\Liip\ImagineBundle\Imagine\Filter\FilterConfiguration::class);
        $filterConfig->method('get')->willReturn(['filters' => ['thumbnail' => ['size' => [20, 20]]]]);

        self::bootKernel([
            "progressive_image" => [
                'responsive_strategy' => [
                    'breakpoints' => [
                        'sm' => 480,
                        'md' => 800,
                    ],
                    'fallback_widths' => ['sm', 'md'],
                    'fallback_sizes' => '100vw',
                    'presets' => [
                        'avatar' => [
                            'widths' => ['sm'],
                            'sizes' => '50px',
                        ],
                    ],
                    'generator' => 'progressive_image.responsive_generator.liip_imagine',
                ],
                'path_decorators' => [
                    'progressive_image.decorator.liip_imagine'
                ]
            ]
        ]);

        self::getContainer()->set('liip_imagine.cache.manager', $cacheManager);
        self::getContainer()->set('liip_imagine.filter.configuration', $filterConfig);

        // Test default preset
        $component = $this->mountTwigComponent(
            name: 'pgi:Image',
            data: [
                'src' => 'test.jpg',
            ]
        );

        $srcset = $component->getSrcSet();
        $this->assertStringContainsString('srcset="', $srcset);
        $this->assertStringContainsString('/cache/progressive_image_filter_sm/test.jpg 480w', $srcset);
        $this->assertStringContainsString('/cache/progressive_image_filter_md/test.jpg 800w', $srcset);
        $this->assertSame('sizes="100vw"', $component->getSizes());

        // Test with preset
        $component = $this->mountTwigComponent(
            name: 'pgi:Image',
            data: [
                'src' => 'test.jpg',
                'preset' => 'avatar'
            ]
        );

        $srcset = $component->getSrcSet();
        $this->assertStringContainsString('/cache/avatar_sm/test.jpg 480w', $srcset);
        $this->assertSame('sizes="50px"', $component->getSizes());
    }
}
