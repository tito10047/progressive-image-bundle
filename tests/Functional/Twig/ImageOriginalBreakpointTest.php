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
                    'breakpoints' => [
                        'mobile' => 320,
                        'desktop' => 1024,
                    ],
                    'fallback_widths' => ['mobile'],
                    'fallback_sizes' => '100vw',
                    'generator' => 'progressive_image.responsive_generator.liip_imagine'
                ]
            ]
        ]);

        self::getContainer()->set('liip_imagine.cache.manager', $cacheManager);

        $html = $this->renderTwigComponent(
            name: "pgi:Image",
            data: [
                "src" => "/test.png",
            ]
        );

        $this->assertStringContainsString('srcset="', $html);
        $this->assertStringContainsString('320w', $html);
        $this->assertStringContainsString('100w', $html);
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
