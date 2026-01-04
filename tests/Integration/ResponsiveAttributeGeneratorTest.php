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
					],
				],
			],
		]);

		$container = self::getContainer();
		/** @var ResponsiveAttributeGenerator $generator */
		$generator = $container->get(ResponsiveAttributeGenerator::class);

		// We mock the URL generator to see what heights are calculated
		$urlGenerator = $this->createMock(ResponsiveImageUrlGeneratorInterface::class);

		// We use reflection to set the mock if necessary, or try to push it via the container
		// But in this integration test we can verify the results directly if we know how the calculation works in ResponsiveAttributeGenerator

		$assignments = [
			new BreakpointAssignment('md', 12, 'landscape'),
			new BreakpointAssignment('sm', 12, 'portrait'),
			new BreakpointAssignment('xs', 12, 'square'),
		];

		// ResponsiveAttributeGenerator in generate() calls calculateDimensions and then generateUrl
		// generateUrl calls resolveRatio and calculates targetH = (int) round($basePixelWidth / $ratio)

		// Assuming bootstrap defaults (md: 768px -> max_container 720px)
		// landscape: 16/9 = 1.777...
		// md:12 -> 720px width. 720 / (16/9) = 720 * 9 / 16 = 45 * 9 = 405px

		// portrait: 3/4 = 0.75
		// sm:12 -> 540px width. 540 / (3/4) = 540 * 4 / 3 = 180 * 4 = 720px

		// square (400x500): 400/500 = 0.8
		// xs:12 -> fluid, max-width estimate 1920px. 1920 / 0.8 = 2400px (if it's xs, then min_viewport 0)

		$result = $generator->generate('test.jpg', $assignments, 2000, false);

		$this->assertStringContainsString('405', $result['srcset']);
		$this->assertStringContainsString('720', $result['srcset']);
		$this->assertStringContainsString('2400', $result['srcset']);
	}
}
