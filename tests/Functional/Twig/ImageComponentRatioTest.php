<?php

namespace Tito10047\ProgressiveImageBundle\Tests\Functional\Twig;

use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\UX\TwigComponent\Test\InteractsWithTwigComponents;
use Tito10047\ProgressiveImageBundle\Tests\Integration\PGITestCase;

class ImageComponentRatioTest extends PGITestCase {

	use InteractsWithTwigComponents;

	private string     $tempDir;
	private Filesystem $fs;

	protected function setUp(): void {
		$this->fs      = new Filesystem();
		$this->tempDir = sys_get_temp_dir() . '/progressive_image_test_controller_' . uniqid();
		$this->fs->mkdir($this->tempDir);
		$this->fs->copy(__DIR__ . '/../../Fixtures/test_800x800.png', $this->tempDir . '/test.png');
	}

	public function testRenderWithResponsiveStrategyAndNamedRatios(): void {
		if (!class_exists(CacheManager::class)) {
			$this->markTestSkipped('LiipImagineBundle is not installed.');
		}

		$this->bootKernel([
			'progressive_image' => [
				'resolvers' => [
					'temp' => [
						'type'  => 'filesystem',
						'roots' => [$this->tempDir],
					],
				],
				'resolver'  => 'temp',
				'responsive_strategy' => [
					'grid'   => [
						'framework' => 'tailwind',
					],
					'ratios' => [
						'landscape' => '16/9',
						'portrait'  => '3/4',
						'square'    => '1/1',
					],
				],
			],
		]);

		$html = $this->renderTwigComponent(
			name: 'pgi:Image',
			data: [
				'src'           => '/test.png',
				'alt'           => 'Hero Background',
				'fetchpriority' => 'high',
				'preload'       => true,
				'decoding'      => 'sync',
				'sizes' => '2xl:12@landscape xl:12@landscape lg:12@landscape md:12@landscape sm:12@landscape default:12@landscape',
				'class'         => 'w-full h-full object-cover brightness-[0.1] animate-ken-burns',
			]
		);

		// Očakávame, že v srcset budú URL adresy s vypočítanou výškou podľa pomeru 16/9.
		// Originál má 800px.
		// 2xl: max_container 1536. 12/12 * 1536 = 1536. 1536 > 800 -> width=800, height=800/(16/9) = 450.
		// xl: max_container 1280. 12/12 * 1280 = 1280. 1280 > 800 -> width=800, height=450.
		// lg: max_container 1024. 12/12 * 1024 = 1024. 1024 > 800 -> width=800, height=450.
		// md: max_container 768. 12/12 * 768 = 768. 768 < 800 -> width=768, height=768/(16/9) = 432.
		// sm: max_container 640. 12/12 * 640 = 640. 640 < 800 -> width=640, height=640/(16/9) = 360.
		// default: 100vw -> 100/100 * 1920 = 1920. 1920 > 800 -> width=800, height=450.

		// Nad 800px by mali byť 800px
		$this->assertStringContainsString('height=450&path=%2Ftest.png&width=800 1536w', $html);
		$this->assertStringContainsString('height=450&path=%2Ftest.png&width=800 1280w', $html);
		$this->assertStringContainsString('height=450&path=%2Ftest.png&width=800 1024w', $html);

		// Pod 800px by mali byť presné šírky
		$this->assertStringContainsString('height=432&path=%2Ftest.png&width=768 768w', $html);
		$this->assertStringContainsString('height=360&path=%2Ftest.png&width=640 640w', $html);

		$this->assertStringNotContainsString('min-width=1024', $html);
		$this->assertStringNotContainsString('min-width=768', $html);
		$this->assertStringNotContainsString('min-width=640', $html);

		$this->assertStringContainsString('1536w', $html);
		$this->assertStringContainsString('1024w', $html);
		$this->assertStringContainsString('768w', $html);
		$this->assertStringContainsString('640w', $html);
	}

}
