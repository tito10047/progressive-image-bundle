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
use Tito10047\ProgressiveImageBundle\UrlGenerator\ResponsiveImageUrlGeneratorInterface;

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
						'landscape' => '16/9',
						'portrait'  => '3/4',
						'square'    => '400x500',
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
}
