<?php

/*
 * This file is part of the Progressive Image Bundle.
 *
 * (c) Jozef Môstka <https://github.com/tito10047/progressive-image-bundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace Tito10047\ProgressiveImageBundle\Tests\Unit\Service;

use Liip\ImagineBundle\Exception\Imagine\Filter\NonExistingFilterException;
use Liip\ImagineBundle\Imagine\Filter\FilterConfiguration;
use PHPUnit\Framework\TestCase;
use Tito10047\ProgressiveImageBundle\Service\LiipImagineRuntimeConfigGenerator;

class LiipImagineRuntimeConfigGeneratorTest extends TestCase
{
	private FilterConfiguration $filterConfiguration;
	private LiipImagineRuntimeConfigGenerator $generator;

	protected function setUp(): void
	{
		if (!class_exists(FilterConfiguration::class)) {
			$this->markTestSkipped('LiipImagineBundle is not installed.');
		}
		$this->filterConfiguration = $this->createMock(FilterConfiguration::class);
		$this->generator = new LiipImagineRuntimeConfigGenerator($this->filterConfiguration);
	}

	public function testGenerateWithFilter(): void
	{
		$this->filterConfiguration->expects($this->once())
			->method('get')
			->with('my_filter')
			->willReturn(['filters' => ['relative_resize' => ['w' => 100]]]);

		$result = $this->generator->generate(200, 150, 'my_filter');

		$this->assertEquals('my_filter_200x150', $result['filterName']);
		$this->assertEquals([
			'filters' => [
				'relative_resize' => ['w' => 100],
				'thumbnail' => [
					'size' => [200, 150],
					'mode' => 'outbound',
				],
			],
		], $result['config']);
	}

	public function testGenerateWithoutFilter(): void
	{
		$this->filterConfiguration->expects($this->never())
			->method('get');

		$result = $this->generator->generate(300, 200);

		$this->assertEquals('300x200', $result['filterName']);
		$this->assertEquals([
			'filters' => [
				'thumbnail' => [
					'size' => [300, 200],
					'mode' => 'outbound',
				],
			],
		], $result['config']);
	}

	public function testGenerateWithPointInterest(): void
	{
		$this->filterConfiguration->expects($this->never())
			->method('get');

		// PoI 50x50 na 1000x1000 obrázku, cieľ 200x100
		// stred je 500x500
		// start by mal byť 500 - (200/2) = 400, 500 - (100/2) = 450
		$result = $this->generator->generate(200, 100, null, '50x50', 1000, 1000);

		$this->assertEquals('200x100_50x50', $result['filterName']);
		$this->assertEquals([
			'filters' => [
				'crop' => [
					'start' => [400, 450],
					'size' => [200, 100],
				],
				'thumbnail' => [
					'size' => [200, 100],
					'mode' => 'outbound',
				],
			],
		], $result['config']);
	}

	public function testGenerateWithPointInterestAtEdges(): void
	{
		// PoI 0x0 (ľavý horný roh) na 1000x1000, cieľ 200x100
		// stred je 0x0
		// start by mal byť 0 - 100 = -100, 0 - 50 = -50 -> orezané na 0x0
		$result = $this->generator->generate(200, 100, null, '0x0', 1000, 1000);

		$this->assertEquals([0, 0], $result['config']['filters']['crop']['start']);

		// PoI 100x100 (pravý dolný roh)
		// stred je 1000x1000
		// start by mal byť 1000 - 100 = 900, 1000 - 50 = 950
		// ale max start je orig - target: 1000 - 200 = 800, 1000 - 100 = 900
		$result = $this->generator->generate(200, 100, null, '100x100', 1000, 1000);

		$this->assertEquals([800, 900], $result['config']['filters']['crop']['start']);
	}
}
