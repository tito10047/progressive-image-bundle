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

use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\UX\TwigComponent\Test\InteractsWithTwigComponents;
use Tito10047\ProgressiveImageBundle\Tests\Integration\PGITestCase;

class FrameworkGridTest extends PGITestCase
{
    use InteractsWithTwigComponents;

    private string     $tempDir;
    private Filesystem $fs;

    protected function setUp(): void {
        if (!class_exists(CacheManager::class)) {
            $this->markTestSkipped('LiipImagineBundle is not installed.');
        }
        $this->fs      = new Filesystem();
        $this->tempDir = sys_get_temp_dir() . '/progressive_image_test_framework_' . uniqid();
        $this->fs->mkdir($this->tempDir);
    }

    protected function tearDown(): void {
        if (isset($this->fs)) {
            $this->fs->remove($this->tempDir);
        }
        parent::tearDown();
    }

    public function testBootstrapFrameworkGrid(): void
    {
        $this->_bootKernel([
            'progressive_image' => [
                'responsive_strategy' => [
                    'grid' => [
                        'framework' => 'bootstrap',
                        'columns' => 100
                    ],
                ],
            ],
        ]);

        $html = $this->renderTwigComponent(
            name: "pgi:Image",
            data: [
                "src" => "/test.png",
                "sizes" => "xs-1 md-2 xxl-1",
            ]
        );

        $this->assertStringContainsString('sizes="(min-width: 1400px) 13px, (min-width: 768px) 14px, 1vw"', $html);
        $this->assertStringContainsString('13w', $html);
        $this->assertStringContainsString('14w', $html);
        $this->assertStringContainsString('19w', $html);
    }

    public function testBootstrapFrameworkGridOverride(): void
    {
        $this->_bootKernel([
            'progressive_image' => [
                'responsive_strategy' => [
                    'grid' => [
                        'framework' => 'bootstrap',
                        'columns' => 12,
                        'layouts' => [
                            'md' => ['max_container' => 600]
                        ]
                    ],
                ],
            ],
        ]);

        $html = $this->renderTwigComponent(
            name: "pgi:Image",
            data: [
                "src" => "/test.png",
                "sizes" => "md-1", // 1/12 * 600 = 50px
            ]
        );

        $this->assertStringContainsString('sizes="(min-width: 768px) 50px"', $html);
        $this->assertStringContainsString('50w', $html);
    }

    public function testTailwindFrameworkGrid(): void
    {
        $this->_bootKernel([
            'progressive_image' => [
                'responsive_strategy' => [
                    'grid' => [
                        'framework' => 'tailwind',
                        'columns' => 40
                    ],
                ],
            ],
        ]);

        $html = $this->renderTwigComponent(
            name: "pgi:Image",
            data: [
                "src" => "/test.png",
                "sizes" => "default-1 md-2 2xl-1",
            ]
        );

        $this->assertStringContainsString('sizes="(min-width: 1536px) 38px, (min-width: 768px) 38px, 3vw"', $html);
        $this->assertStringContainsString('38w', $html);
        $this->assertStringContainsString('48w', $html);
    }

    protected function _bootKernel(array $extraOptions): void
    {
        $imagePath = $this->tempDir . '/test.png';
        $this->fs->copy(__DIR__ . '/../../Fixtures/test.png', $imagePath);

        $options = array_merge_recursive([
            "progressive_image" => [
                'resolvers' => [
                    'test' => [
                        'type'  => 'filesystem',
                        'roots' => [realpath($this->tempDir)]
                    ]
                ],
                'resolver'  => 'test'
            ]
        ], $extraOptions);

        static::bootKernel($options);
    }
}
