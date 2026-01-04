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

use Symfony\UX\TwigComponent\Test\InteractsWithTwigComponents;
use Tito10047\ProgressiveImageBundle\Tests\Integration\PGITestCase;

class CacheDisabledTest extends PGITestCase
{
    use InteractsWithTwigComponents;

    public function testKernelBootsWithCacheDisabledAndNonTagAwareCache(): void
    {
		// Here we use 'cache.app' (which in the test kernel is 'cache.adapter.array' without tags,
		// if we don't explicitly reconfigure it to be taggable)
        self::bootKernel([
            'progressive_image' => [
                'image_cache_enabled' => false,
                'image_cache_service' => 'cache.app',
            ],
        ]);

        $this->assertTrue(self::$booted);

		// Verify that the component can be rendered
        $rendered = $this->renderTwigComponent(
            name: 'pgi:Image',
            data: [
                'src' => 'images/test.jpg',
                'alt' => 'Test Alt',
            ]
        );

        $this->assertStringContainsString('progressive-image-container', (string) $rendered);
        $this->assertStringContainsString('alt="Test Alt"', (string) $rendered);
    }
}
