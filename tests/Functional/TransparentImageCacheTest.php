<?php

namespace Tito10047\ProgressiveImageBundle\Tests\Functional;

use Tito10047\ProgressiveImageBundle\Event\TransparentImageCacheSubscriber;
use Symfony\UX\TwigComponent\Test\InteractsWithTwigComponents;
use Tito10047\ProgressiveImageBundle\Tests\Integration\PGITestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class TransparentImageCacheTest extends PGITestCase
{
    use InteractsWithTwigComponents;

    public function testImageComponentIsCached(): void
    {
        $cache = new ArrayAdapter();

        self::bootKernel([
            'progressive_image' => [
                'image_cache_enabled' => true,
            ]
        ]);

        // Nahradíme cache v kontajneri našou ArrayAdapter, aby sme mohli kontrolovať obsah
        self::getContainer()->set('cache.app', $cache);

        // 1. Prvé renderovanie - malo by sa uložiť do keše
        $rendered1 = $this->renderTwigComponent(
            name: 'pgi:Image',
            data: [
                'src' => 'images/test.jpg',
                'alt' => 'Test Alt'
            ]
        );

        $this->assertStringContainsString('progressive-image-container', (string) $rendered1);
        $this->assertStringContainsString('alt="Test Alt"', (string) $rendered1);

        // Skontrolujeme či je v keši niečo s prefixom pgi_comp_
        $cacheItems = $cache->getValues();
        $this->assertNotEmpty($cacheItems, 'Cache should not be empty after first render');
        
        $cacheKey = null;
        foreach (array_keys($cacheItems) as $key) {
            if (str_starts_with($key, 'pgi_comp_')) {
                $cacheKey = $key;
                break;
            }
        }
        $this->assertNotNull($cacheKey, 'Cache key starting with pgi_comp_ should exist');

        // 2. Druhé renderovanie - malo by sa vrátiť z keše
        // Zmeníme obsah v keši, aby sme overili, že sa naozaj vracia z keše
        $item = $cache->getItem($cacheKey);
        $item->set('CACHED_CONTENT');
        $cache->save($item);

        $rendered2 = $this->renderTwigComponent(
            name: 'pgi:Image',
            data: [
                'src' => 'images/test.jpg',
                'alt' => 'Test Alt'
            ]
        );

        $this->assertEquals('CACHED_CONTENT', (string) $rendered2);
    }

    public function testCustomCacheServiceIsUsed(): void
    {
        self::bootKernel([
            'framework' => [
                'cache' => [
                    'pools' => [
                        'my_custom_cache_pool' => [
                            'adapter' => 'cache.adapter.array',
							'public' => true,
                        ],
                    ],
                ],
            ],
            'progressive_image' => [
                'image_cache_enabled' => true,
                'image_cache_service' => 'my_custom_cache_pool',
            ]
        ]);

        $container = self::getContainer();
        
        // Získame subscribera aby sme videli čo má injektované
        /** @var TransparentImageCacheSubscriber $subscriber */
        $subscriber = $container->get(TransparentImageCacheSubscriber::class);
        $reflection = new \ReflectionClass($subscriber);
        $property = $reflection->getProperty('cache');
        $property->setAccessible(true);
        $injectedCache = $property->getValue($subscriber);
        
        $this->assertInstanceOf(ArrayAdapter::class, $injectedCache);

        // Renderujeme komponent
        $this->renderTwigComponent(
            name: 'pgi:Image',
            data: [
                'src' => 'images/test.jpg',
                'alt' => 'Custom Cache Test'
            ]
        );

    }
}
