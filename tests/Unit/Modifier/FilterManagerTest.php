<?php

/*
 * This file is part of the Progressive Image Bundle.
 *
 * (c) Jozef Môstka <https://github.com/tito10047/progressive-image-bundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tito10047\ProgressiveImageBundle\Tests\Unit\Modifier;

use PHPUnit\Framework\TestCase;
use Tito10047\ProgressiveImageBundle\Modifier\CoreFilterModifier;
use Tito10047\ProgressiveImageBundle\Modifier\FilterManager;
use Tito10047\ProgressiveImageBundle\Modifier\FilterModifierInterface;

class FilterManagerTest extends TestCase {

	public function testApplyFiltersWithCoreModifier(): void {
		$coreModifier = new CoreFilterModifier();
		$manager      = new FilterManager([$coreModifier]);

		$options = $manager->applyFilters('circle');
		$this->assertEquals(['filter' => 'circle'], $options);

		$options = $manager->applyFilters('grayscale|sepia');
		$this->assertEquals(['filter' => 'sepia'], $options);
	}

	public function testApplyFiltersWithCustomModifier(): void {
		$customModifier = $this->createMock(FilterModifierInterface::class);
		$customModifier->method('supports')->willReturnCallback(fn($n) => 'border-5' === $n);
		$customModifier->method('modify')->willReturnCallback(function ($n, $o) {
			$o['border'] = 5;

			return $o;
		});

		$coreModifier = new CoreFilterModifier();

		// Custom modifier first
		$manager = new FilterManager([$customModifier, $coreModifier]);

		$options = $manager->applyFilters('circle|border-5');
		$this->assertEquals(['filter' => 'circle', 'border' => 5], $options);
	}

	public function testApplyFiltersFallback(): void {
		$manager = new FilterManager([]);

		$options = $manager->applyFilters('unknown');
		$this->assertEquals(['custom_filters' => ['unknown']], $options);
	}
}
