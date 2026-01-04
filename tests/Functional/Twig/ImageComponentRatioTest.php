<?php

namespace Tito10047\ProgressiveImageBundle\Tests\Functional\Twig;

use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Symfony\UX\TwigComponent\Test\InteractsWithTwigComponents;
use Tito10047\ProgressiveImageBundle\Tests\Integration\PGITestCase;

class ImageComponentRatioTest extends PGITestCase {

	use InteractsWithTwigComponents;

	public function testRenderWithResponsiveStrategyAndNamedRatios(): void {
		if (!class_exists(CacheManager::class)) {
			$this->markTestSkipped('LiipImagineBundle is not installed.');
		}

		$this->_bootKernel([
			'progressive_image' => [
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
				'sizes'         => '2xl:12@landscape lg:12@landscape md:12@landscape sm:12@landscape md:12@landscape',
				'class'         => 'w-full h-full object-cover brightness-[0.1] animate-ken-burns',
			]
		);

		// Očakávame, že v srcset budú URL adresy s vypočítanou výškou podľa pomeru 16/9.
		// Pre šírku 1024w by mala byť výška 1024 / (16/9) = 1024 * 9 / 16 = 576.
		// Používateľ reportoval, že dostáva 1024x1024, 768x768, 640x640.

		$this->assertStringContainsString('width=1024', $html);
		$this->assertStringContainsString('height=576', $html, 'Height should be 576 for width 1024 with 16/9 ratio');

		$this->assertStringContainsString('width=768', $html);
		$this->assertStringContainsString('height=432', $html, 'Height should be 432 for width 768 with 16/9 ratio');

		$this->assertStringContainsString('width=640', $html);
		$this->assertStringContainsString('height=360', $html, 'Height should be 360 for width 640 with 16/9 ratio');

		$this->assertStringNotContainsString('height=1024', $html);
		$this->assertStringNotContainsString('height=768', $html);
		$this->assertStringNotContainsString('height=640', $html);
	}

	protected function _bootKernel(array $extraOptions): void {
		static::bootKernel($extraOptions);
	}
}
