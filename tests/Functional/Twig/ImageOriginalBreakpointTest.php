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

use Symfony\Component\Filesystem\Filesystem;
use Symfony\UX\TwigComponent\Test\InteractsWithTwigComponents;
use Tito10047\ProgressiveImageBundle\Tests\Integration\PGITestCase;

class ImageOriginalBreakpointTest extends PGITestCase
{
    use InteractsWithTwigComponents;
    private string $tempDir;
    private Filesystem $fs;

    protected function setUp(): void
    {
        $this->fs = new Filesystem();
        $this->tempDir = sys_get_temp_dir().'/progressive_image_test_'.uniqid();
        $this->fs->mkdir($this->tempDir);
    }

    public function testRenderWithOriginalBreakpoint(): void
    {
        $this->_bootKernel([
            'progressive_image' => [
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

        $html = $this->renderTwigComponent(
            name: 'pgi:Image',
            data: [
                'src' => '/test.png',
                'sizes' => 'mobile:12 desktop:1',
            ]
        );

        $this->assertStringContainsString('srcset="', $html);
        $this->assertStringContainsString('100w', $html);
        $this->assertStringContainsString('1920w', $html);
    }

    private function _bootKernel(array $extraOptions = []): void
    {
        $imagePath = $this->tempDir.'/test.png';
        $this->fs->copy(__DIR__.'/../../Fixtures/test.png', $imagePath);

        $options = array_merge_recursive([
            'progressive_image' => [
                'resolvers' => [
                    'test' => [
                        'type' => 'filesystem',
                        'roots' => [realpath($this->tempDir)],
                    ],
                ],
                'resolver' => 'test',
            ],
        ], $extraOptions);

        self::bootKernel($options);
    }
}
