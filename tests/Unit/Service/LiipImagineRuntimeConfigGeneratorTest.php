<?php

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

	public function testGenerateWithNonExistingFilter(): void
	{
		$this->filterConfiguration->expects($this->once())
			->method('get')
			->with('non_existing')
			->willThrowException(new NonExistingFilterException());

		$result = $this->generator->generate(100, 100, 'non_existing');

		$this->assertEquals('non_existing_100x100', $result['filterName']);
		$this->assertEquals([
			'filters' => [
				'thumbnail' => [
					'size' => [100, 100],
					'mode' => 'outbound',
				],
			],
		], $result['config']);
	}
}
