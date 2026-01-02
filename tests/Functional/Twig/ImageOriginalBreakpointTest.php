<?php

namespace Tito10047\ProgressiveImageBundle\Tests\Functional\Twig;

use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\UX\TwigComponent\Test\InteractsWithTwigComponents;
use Tito10047\ProgressiveImageBundle\Tests\Integration\PGITestCase;

class ImageOriginalBreakpointTest extends PGITestCase
{
    use InteractsWithTwigComponents;
	private string     $tempDir;
	private Filesystem $fs;

	protected function setUp(): void {
		$this->fs      = new Filesystem();
		$this->tempDir = sys_get_temp_dir() . '/progressive_image_test_' . uniqid();
		$this->fs->mkdir($this->tempDir);
	}

	public function testRenderWithOriginalBreakpoint(): void
    {
        if (!class_exists(CacheManager::class)) {
            $this->markTestSkipped('LiipImagineBundle is not installed.');
        }
        $cacheManager = $this->createMock(CacheManager::class);
        $cacheManager->method('getBrowserPath')
            ->willReturnCallback(function($path, $filter) {
                return 'http://localhost/media/cache/resolve/' . $filter . $path;
            });

        $this->_bootKernel([
            "progressive_image" => [
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
                ]
            ]
        ]);

        self::getContainer()->set('liip_imagine.cache.manager', $cacheManager);

        $html = $this->renderTwigComponent(
            name: "pgi:Image",
            data: [
                "src" => "/test.png",
                "sizes" => "mobile-12 desktop-1",
            ]
        );

        $this->assertStringContainsString('srcset="', $html);
        $this->assertStringContainsString('100w', $html);
        $this->assertStringNotContainsString('1920w', $html);
    }

	private function _bootKernel(array $extraOptions = []): void {
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

		self::bootKernel($options);
	}
}
