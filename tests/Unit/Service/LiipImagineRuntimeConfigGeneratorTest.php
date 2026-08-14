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

        // POI at pixel (500, 500) on a 1000x1000 image, target 200x100.
        // origRatio=1.0 < targetRatio=2.0 → constrain by width, crop height.
        // cropW=1000, cropH=500; startX=0, startY=250.
        $result = $this->generator->generate(200, 100, null, '500x500', 1000, 1000);

        $this->assertEquals('200x100_500x500', $result['filterName']);
        $this->assertEquals([
            'filters' => [
                'crop' => [
                    'start' => [0, 250],
                    'size' => [1000, 500],
                ],
                'thumbnail' => [
                    'size' => [200, 100],
                    'mode' => 'inset',
                ],
            ],
        ], $result['config']);
    }

    public function testGenerateWithPointInterestAtEdges(): void
    {
        // POI at pixel (0, 0) — upper-left corner, 1000x1000, target 200x100.
        // cropW=1000, cropH=500; start clamped to (0, 0).
        $result = $this->generator->generate(200, 100, null, '0x0', 1000, 1000);

        $this->assertEquals([0, 0], $result['config']['filters']['crop']['start']);
        $this->assertEquals([1000, 500], $result['config']['filters']['crop']['size']);

        // POI at pixel (1000, 1000) — lower-right corner.
        // startX = 1000-500=500, clamped to max (1000-1000=0) → 0.
        // startY = 1000-250=750, clamped to max (1000-500=500) → 500.
        $result = $this->generator->generate(200, 100, null, '1000x1000', 1000, 1000);

        $this->assertEquals([0, 500], $result['config']['filters']['crop']['start']);
        $this->assertEquals([1000, 500], $result['config']['filters']['crop']['size']);
    }

    public function testGenerateWithExtraConfig(): void
    {
        $imageConfigs = [
            'quality' => 75,
            'post_processors' => [
                'cwebp' => ['q' => 75, 'm' => 6],
            ],
        ];
        $generator = new LiipImagineRuntimeConfigGenerator($this->filterConfiguration, $imageConfigs);

        $result = $generator->generate(200, 150);

        $this->assertStringContainsString('_', $result['filterName']);
        $this->assertEquals(75, $result['config']['quality']);
        $this->assertEquals(['q' => 75, 'm' => 6], $result['config']['post_processors']['cwebp']);
    }

    public function testGenerateWithContext(): void
    {
        $context = ['filter' => 'circle', 'foo' => 'bar'];
        $result = $this->generator->generate(200, 150, null, null, null, null, $context);

        $this->assertStringContainsString('_', $result['filterName']);
        $this->assertEquals('circle', $result['config']['filter']);
        $this->assertEquals('bar', $result['config']['foo']);
    }
}
