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

		// Mockujeme URL generátor, aby sme videli aké výšky sa počítajú
		$urlGenerator = $this->createMock(ResponsiveImageUrlGeneratorInterface::class);

		// Použijeme reflexiu na nastavenie mocku, ak je to potrebné, alebo ho skúsime podhodiť cez kontajner
		// Ale v tomto integračnom teste môžeme overiť výsledky priamo ak vieme ako funguje výpočet v ResponsiveAttributeGenerator

		$assignments = [
			new BreakpointAssignment('md', 12, 'landscape'),
			new BreakpointAssignment('sm', 12, 'portrait'),
			new BreakpointAssignment('xs', 12, 'square'),
		];

		// ResponsiveAttributeGenerator v generate() volá calculateDimensions a potom generateUrl
		// generateUrl volá resolveRatio a počíta targetH = (int) round($basePixelWidth / $ratio)

		// Predpokladáme bootstrap defaulty (md: 768px -> max_container 720px)
		// landscape: 16/9 = 1.777...
		// md-12 -> 720px šírka. 720 / (16/9) = 720 * 9 / 16 = 45 * 9 = 405px

		// portrait: 3/4 = 0.75
		// sm-12 -> 540px šírka. 540 / (3/4) = 540 * 4 / 3 = 180 * 4 = 720px

		// square (400x500): 400/500 = 0.8
		// xs-12 -> fluid, max-width odhad 1920px. 1920 / 0.8 = 2400px (ak je to xs, tak min_viewport 0)

		$result = $generator->generate('test.jpg', $assignments, 2000, false);

		$this->assertStringContainsString('405', $result['srcset']);
		$this->assertStringContainsString('720', $result['srcset']);
		$this->assertStringContainsString('2400', $result['srcset']);
	}
}
