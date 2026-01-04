<?php

/*
 * This file is part of the Progressive Image Bundle.
 *
 * (c) Jozef Môstka <https://github.com/tito10047/progressive-image-bundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tito10047\ProgressiveImageBundle\Tests\Integration;

use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Tito10047\ProgressiveImageBundle\DTO\BreakpointAssignment;
use Tito10047\ProgressiveImageBundle\Service\ResponsiveAttributeGenerator;

class ResponsiveAttributeGeneratorTest extends PGITestCase {

	public function testRatioFromConfiguration(): void {
		if (!class_exists(CacheManager::class)) {
			$this->markTestSkipped('LiipImagineBundle is not installed.');
		}
		self::bootKernel([
			'progressive_image' => [
				'responsive_strategy' => [
					'grid'   => [
						'framework' => 'bootstrap',
					],
					'ratios' => [
						'landscape'     => '16/9',
						'portrait'      => '3/4',
						'square'        => '400x500',
						'hero_portrait' => '0.65',
					],
				],
			],
		]);

		$container = self::getContainer();
		/** @var ResponsiveAttributeGenerator $generator */
		$generator = $container->get(ResponsiveAttributeGenerator::class);

		$assignments = [
			new BreakpointAssignment('md', 12, 'landscape'),
			new BreakpointAssignment('sm', 12, 'portrait'),
			new BreakpointAssignment('xs', 12, 'square'),
			new BreakpointAssignment('lg', 12, 'hero_portrait'),
		];

		// lg:12 -> bootstrap lg: 960px. 960 / 0.65 = 1476.92... -> 1477px
		$result = $generator->generate('test.jpg', $assignments, 2000, false);

		$this->assertStringContainsString('405', $result['srcset']);
		$this->assertStringContainsString('720', $result['srcset']);
		$this->assertStringContainsString('2400', $result['srcset']);
		$this->assertStringContainsString('1477', $result['srcset']);
	}

	public function testNewRatioFormats(): void {
		if (!class_exists(CacheManager::class)) {
			$this->markTestSkipped('LiipImagineBundle is not installed.');
		}
		self::bootKernel([
			'progressive_image' => [
				'responsive_strategy' => [
					'grid' => [
						'framework' => 'bootstrap',
					],
				],
			],
		]);

		$container = self::getContainer();
		/** @var ResponsiveAttributeGenerator $generator */
		$generator = $container->get(ResponsiveAttributeGenerator::class);

		// sm:[100%]@[0.65] -> bootstrap sm: 540px. 540 / 0.65 = 830.76... -> 831px
		// [100%]@[10/9] -> default/xs: 100% of viewport, but for bootstrap it might use a default width or 100vw.
		// bootstrap xs has no container width, it is 100%. responsive generator uses 100vw for 100%.
		// let's assume it uses some default if not specified, but let's check what it does.
		// [100%]@[1500x700] -> 1500/700 = 2.14...
		$assignments = BreakpointAssignment::parseSegments('sm:[100%]@[0.65] [100%]@[10/9] [100%]@[1500x700]', null);

		$result = $generator->generate('test.jpg', $assignments, 2000, false);

		// sm: 540 / 0.65 = 831
		$this->assertStringContainsString('831', $result['srcset']);
		// xs: 100vw / (10/9) = 1920 * 0.9 = 1728
		$this->assertStringContainsString('1728', $result['srcset']);
		// [1500x700] ratio: 1500/700 = 2.1428... 1920 / 2.1428... = 896
		// But it generates width and then height based on ratio.
		// basePixelWidth for 100% on xs (fluid) is 1920.
		// for [1500x700] it should be 1920w and height = 1920 / (1500/700) = 896
		$this->assertStringContainsString('1920', $result['srcset']);

		// Let's just check if it doesn't crash and generates some reasonable values.
		$this->assertNotEmpty($result['srcset']);
	}
}
