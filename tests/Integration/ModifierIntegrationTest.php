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

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tito10047\ProgressiveImageBundle\DTO\BreakpointAssignment;
use Tito10047\ProgressiveImageBundle\Modifier\FilterModifierInterface;
use Tito10047\ProgressiveImageBundle\Modifier\ModifierInterface;
use Tito10047\ProgressiveImageBundle\Modifier\ModifierProvider;
use Tito10047\ProgressiveImageBundle\Service\ResponsiveAttributeGenerator;

class TestModifier implements ModifierInterface {

	public function supports(string $modifier): bool {
		return 'circle' === $modifier;
	}

	public function modify(string $modifier, array $context): array {
		$context['circle'] = true;

		return $context;
	}
}

class CustomFilter implements ModifierInterface {

	public function supports(string $modifier): bool {
		return 'circle' === $modifier;
	}

	public function modify(string $modifier, array $context): array {
		$context['filter'] = 'custom_circle';

		return $context;
	}
}

class ModifierIntegrationTest extends TestCase {

	public function testModifiersAreRegisteredAndApplied(): void {
		if (!class_exists(\Liip\ImagineBundle\LiipImagineBundle::class)) {
			$this->markTestSkipped('LiipImagineBundle is not installed.');
		}
		$kernel = new ProgressiveImageTestingKernel([
			'progressive_image' => [
				'responsive_strategy' => [
					'grid'   => [
						'layouts' => [
							'md' => ['min_viewport' => 768, 'max_container' => 720],
						],
						'columns' => 12,
					],
					'ratios' => [
						'landscape' => '16/9',
					],
				],
			],
		]);

		$kernel->setCustomConfiguration(function (ContainerBuilder $container) {
			$container->register(TestModifier::class)
				->addTag('progressive_image.modifier');
		});

		$kernel->boot();
		$container = $kernel->getContainer()->get('test.service_container');

		$this->assertTrue($container->has(ModifierProvider::class) || $kernel->getContainer()->has(ModifierProvider::class));

		/** @var ResponsiveAttributeGenerator $generator */
		$generator = $container->has(ResponsiveAttributeGenerator::class)
			? $container->get(ResponsiveAttributeGenerator::class)
			: $kernel->getContainer()->get(ResponsiveAttributeGenerator::class);

		$result = $generator->generate('test.jpg', [
			new BreakpointAssignment('md', 6, 'landscape', null, null, null, ['circle']),
		], 1000, false);

		$this->assertStringContainsString('circle=1', $result['srcset']);
		// CoreFilterModifier ties it to 'filter' => 'circle'
		$this->assertStringContainsString('filter=circle', $result['srcset']);
	}

	public function testFilterPriority(): void {
		if (!class_exists(\Liip\ImagineBundle\LiipImagineBundle::class)) {
			$this->markTestSkipped('LiipImagineBundle is not installed.');
		}
		$kernel = new ProgressiveImageTestingKernel([
			'progressive_image' => [
				'responsive_strategy' => [
					'grid'   => [
						'layouts' => [
							'md' => ['min_viewport' => 768, 'max_container' => 720],
						],
						'columns' => 12,
					],
					'ratios' => [
						'landscape' => '16/9',
					],
				],
			],
		]);

		$kernel->setCustomConfiguration(function (ContainerBuilder $container) {
			$container->register(CustomFilter::class)
				->addTag('progressive_image.modifier'); // default priority 0 > -100
		});

		$kernel->boot();
		$container = $kernel->getContainer()->get('test.service_container');

		/** @var ResponsiveAttributeGenerator $generator */
		$generator = $container->has(ResponsiveAttributeGenerator::class)
			? $container->get(ResponsiveAttributeGenerator::class)
			: $kernel->getContainer()->get(ResponsiveAttributeGenerator::class);

		$result = $generator->generate('test.jpg', [
			new BreakpointAssignment('md', 6, 'landscape', null, null, null, ['circle']),
		], 1000, false);

		// CustomFilter should win
		$this->assertStringContainsString('filter=custom_circle', $result['srcset']);
		$this->assertStringNotContainsString('filter=circle&', $result['srcset']);
		$this->assertStringNotContainsString('filter=circle"', $result['srcset']);
	}
}
