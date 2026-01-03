<?php

namespace Tito10047\ProgressiveImageBundle\Tests\Functional;

use Symfony\UX\TwigComponent\Test\InteractsWithTwigComponents;
use Tito10047\ProgressiveImageBundle\Tests\Integration\PGITestCase;

class CacheDisabledTest extends PGITestCase
{
    use InteractsWithTwigComponents;

    public function testKernelBootsWithCacheDisabledAndNonTagAwareCache(): void
    {
        // Tu používame 'cache.app' (ktorá v testovacom kerneli je 'cache.adapter.array' bez tagov, 
        // ak ju explicitne neprekonfigurujeme na taggable)
        self::bootKernel([
            'progressive_image' => [
                'image_cache_enabled' => false,
                'image_cache_service' => 'cache.app',
            ]
        ]);

        $this->assertTrue(self::$booted);
        
        // Overíme, že komponent sa dá vyrenderovať
        $rendered = $this->renderTwigComponent(
            name: 'pgi:Image',
            data: [
                'src' => 'images/test.jpg',
                'alt' => 'Test Alt'
            ]
        );

        $this->assertStringContainsString('progressive-image-container', (string) $rendered);
        $this->assertStringContainsString('alt="Test Alt"', (string) $rendered);
    }
}
