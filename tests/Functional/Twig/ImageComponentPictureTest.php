<?php

/*
 * This file is part of the Progressive Image Bundle.
 *
 * (c) Jozef Môstka <https://github.com/tito10047/progressive-image-bundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tito10047\ProgressiveImageBundle\Tests\Functional\Twig;

use Symfony\UX\TwigComponent\Test\InteractsWithTwigComponents;
use Tito10047\ProgressiveImageBundle\Tests\Integration\PGITestCase;

class ImageComponentPictureTest extends PGITestCase
{
    use InteractsWithTwigComponents;

    protected function setUp(): void
    {
        $this->_bootKernel();
    }

    public function testRenderWithPictureTag(): void
    {
        $html = $this->renderTwigComponent(
            name: 'pgi:Image',
            data: [
                'src' => '/test.png',
                'sizes' => 'mobile:12 desktop:1',
            ]
        );

        // Overíme prítomnosť <picture> tagu
        $this->assertStringContainsString('<picture>', $html);
        $this->assertStringContainsString('</picture>', $html);

        // Overíme <source> tag pre desktop breakpoint
        $this->assertStringContainsString('<source', $html);
        $this->assertStringContainsString('media="(min-width: 1024px)"', $html);
        $this->assertStringContainsString('sizes="100px"', $html);

        // Overíme <img> tag (fallback/default)
        $this->assertStringContainsString('<img', $html);
        $this->assertStringContainsString('sizes="100vw"', $html);

        // We need to be careful with assertStringNotContainsString if the whole HTML contains 'media=' in <source>
        // Let's check only the img part.
        $imgPart = substr($html, strpos($html, '<img'));
        $this->assertStringNotContainsString('media=', $imgPart);
    }

    private function _bootKernel(): void
    {
        self::bootKernel([
            'progressive_image' => [
                'resolvers' => [
                    'test' => [
                        'type' => 'filesystem',
                        'roots' => [realpath(__DIR__.'/../../Fixtures')],
                    ],
                ],
                'resolver' => 'test',
                'responsive_strategy' => [
                    'grid' => [
                        'columns' => 12,
                        'layouts' => [
                            'desktop' => [
                                'min_viewport' => 1024,
                                'max_container' => 1200,
                            ],
                            'mobile' => [
                                'min_viewport' => 0,
                                'max_container' => null,
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }
}
