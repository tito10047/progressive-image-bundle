<?php

/*
 * This file is part of the Progressive Image Bundle.
 *
 * (c) Jozef Môstka <https://github.com/tito10047/progressive-image-bundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tito10047\ProgressiveImageBundle\Tests\Functional;

use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\UX\TwigComponent\Test\InteractsWithTwigComponents;
use Tito10047\ProgressiveImageBundle\Tests\Integration\PGITestCase;

class TransparentImageCacheTtlTest extends PGITestCase
{
    use InteractsWithTwigComponents;

    public function testTtlIsUsedInCache(): void
    {
        self::bootKernel([
            'progressive_image' => [
                'image_cache_enabled' => true,
                'ttl' => 3600,
            ],
        ]);

        /** @var TagAwareAdapter $cache */
        $cache = self::getContainer()->get('progressive_image.image_cache_service');

        // Mockujeme cache aby sme zachytili volanie expiresAfter
        // Alebo použijeme Reflection na kontrolu Item v ArrayAdapteri (ak je použitý)
        // V PGITestCase sa zdá byť použitý ArrayAdapter (viď TransparentImageCacheTest)

        $this->renderTwigComponent(
            name: 'pgi:Image',
            data: [
                'src' => 'images/test.jpg',
                'ttl' => 123,
            ]
        );

        $innerPool = $cache;
        if ($cache instanceof TagAwareAdapter) {
            $reflection = new \ReflectionClass($cache);
            $property = $reflection->getProperty('pool');
            $property->setAccessible(true);
            $innerPool = $property->getValue($cache);
        }

        $cacheItems = $innerPool->getValues();
        $cacheKey = null;
        foreach (array_keys($cacheItems) as $key) {
            if (str_starts_with($key, 'pgi_comp_')) {
                $cacheKey = $key;
                break;
            }
        }

        $item = $cache->getItem($cacheKey);

        // ArrayAdapter ukladá expirácie v internom poli $expiries
        $reflectionPool = new \ReflectionClass($innerPool);
        if ($reflectionPool->hasProperty('expiries')) {
            $expiriesProp = $reflectionPool->getProperty('expiries');
            $expiriesProp->setAccessible(true);
            $expiries = $expiriesProp->getValue($innerPool);
            $expiry = $expiries[$cacheKey] ?? null;
        } else {
            // Skúsime pôvodný prístup cez Item, ak by to bol iný adaptér
            $reflection = new \ReflectionClass($item);
            $expiryProperty = $reflection->hasProperty('expiry') ? $reflection->getProperty('expiry') : null;
            if ($expiryProperty) {
                $expiryProperty->setAccessible(true);
                $expiry = $expiryProperty->getValue($item);
            } else {
                $expiry = null;
            }
        }

        // Expiry by mal byť cca current time + 123
        $this->assertNotNull($expiry, 'Expiry should not be null');
        $this->assertGreaterThan(time() + 120, $expiry);
        $this->assertLessThanOrEqual(time() + 124, $expiry);
    }
}
