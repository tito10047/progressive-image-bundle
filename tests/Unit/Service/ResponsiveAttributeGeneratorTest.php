<?php

/*
 * This file is part of the Progressive Image Bundle.
 *
 * (c) Jozef Môstka <https://github.com/tito10047/progressive-image-bundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tito10047\ProgressiveImageBundle\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Tito10047\ProgressiveImageBundle\DTO\BreakpointAssignment;
use Tito10047\ProgressiveImageBundle\Service\PreloadCollector;
use Tito10047\ProgressiveImageBundle\Service\ResponsiveAttributeGenerator;
use Tito10047\ProgressiveImageBundle\UrlGenerator\ResponsiveImageUrlGeneratorInterface;

class ResponsiveAttributeGeneratorTest extends TestCase
{
    private array $gridConfig;
    private array $ratioConfig;
    private $urlGenerator;
    private $preloadCollector;
    private ResponsiveAttributeGenerator $generator;

    protected function setUp(): void
    {
        $this->gridConfig = [
            'layouts' => [
                'xs' => ['min_viewport' => 0, 'max_container' => null],
                'sm' => ['min_viewport' => 576, 'max_container' => 540],
                'md' => ['min_viewport' => 768, 'max_container' => 720],
                'lg' => ['min_viewport' => 992, 'max_container' => 960],
                'xl' => ['min_viewport' => 1200, 'max_container' => 1140],
                'xxl' => ['min_viewport' => 1400, 'max_container' => 1320],
            ],
            'columns' => 12,
        ];
        $this->ratioConfig = [
            'square' => 1.0,
            'landscape' => 1.5,
        ];
        $this->urlGenerator = $this->createMock(ResponsiveImageUrlGeneratorInterface::class);
        $this->preloadCollector = $this->createMock(PreloadCollector::class);
        $this->generator = new ResponsiveAttributeGenerator($this->gridConfig, $this->ratioConfig, $this->preloadCollector, $this->urlGenerator);
    }

    public function testGenerateBasic(): void
    {
        $path = 'test.jpg';
        $assignments = [
            new BreakpointAssignment('xs', 12, 'square'),
            new BreakpointAssignment('md', 6, 'landscape'),
        ];
        $originalWidth = 2000;

        // xs: 12/12 * 100vw = 100vw. Pixel width (estimate) = 1920px.
        // md: 6/12 * 720px = 360px.

        $this->urlGenerator->expects($this->exactly(2))
            ->method('generateUrl')
            ->willReturnMap([
                [$path, 360, 240, 'url-360'],
                [$path, 1920, 1920, 'url-1920'],
            ]);

        $result = $this->generator->generate($path, $assignments, $originalWidth, false);

        $this->assertEquals('(min-width: 768px) 360px, 100vw', $result['sizes']);
        $this->assertStringContainsString('url-360 360w', $result['srcset']);
        $this->assertStringNotContainsString('url-720 720w', $result['srcset']); // No more 2x multiplier by default
        $this->assertStringContainsString('url-1920 1920w', $result['srcset']);

		$this->assertArrayHasKey('variables', $result);
		$this->assertEquals('100vw', $result['variables']['--img-width']);
		$this->assertEquals('1', $result['variables']['--img-aspect']);
		$this->assertEquals('360px', $result['variables']['--img-width-md']);
		$this->assertEquals('1.5', $result['variables']['--img-aspect-md']);
    }

    public function testResolveRatioWithDifferentFormats(): void
    {
        $path = 'test.jpg';
        $originalWidth = 2000;

        // Test format "3/4"
        $assignments1 = [new BreakpointAssignment('md', 6, '3/4')];

        $this->urlGenerator->expects($this->once())
            ->method('generateUrl')
            ->with($path, 360, 480)
            ->willReturn('url');

        $this->generator->generate($path, $assignments1, $originalWidth, false);

        // Test format "16-9"
        $assignments2 = [new BreakpointAssignment('md', 6, '16-9')];
        $this->urlGenerator = $this->createMock(ResponsiveImageUrlGeneratorInterface::class);
        $this->generator = new ResponsiveAttributeGenerator($this->gridConfig, $this->ratioConfig, $this->preloadCollector, $this->urlGenerator);

        $this->urlGenerator->expects($this->once())
            ->method('generateUrl')
            ->with($path, 360, (int) round(360 / (16 / 9)))
            ->willReturn('url');

        $this->generator->generate($path, $assignments2, $originalWidth, false);
    }

    public function testUpscalingProtection(): void
    {
        $path = 'test.jpg';
        $assignments = [
            new BreakpointAssignment('md', 6, 'landscape'),
        ];
        $originalWidth = 500;

        // md: 6/12 * 720px = 360px.

        $this->urlGenerator->expects($this->once())
            ->method('generateUrl')
            ->with($path, 360, 240)
            ->willReturn('url-360');

        $result = $this->generator->generate($path, $assignments, $originalWidth, false);

        $this->assertEquals('url-360 360w', $result['srcset']);
    }

	public function testGenerateWithDefaultBreakpoint(): void {
		$path          = 'test.jpg';
		$assignments   = [
			new BreakpointAssignment('default', 12, 'square'),
		];
		$originalWidth = 2000;

		// xs in gridConfig has min_viewport 0.
		$this->urlGenerator->expects($this->once())
			->method('generateUrl')
			->willReturn('url-1920');

		$result = $this->generator->generate($path, $assignments, $originalWidth, false);

		$this->assertEquals('100vw', $result['sizes']);
		$this->assertStringContainsString('url-1920 1920w', $result['srcset']);
		$this->assertEquals('100vw', $result['variables']['--img-width']);
	}

	public function testGenerateWithExplicitDimensions(): void {
		$path          = 'test.jpg';
		$assignments   = [
			new BreakpointAssignment('xxl', 0, '430x370', 430, 370),
			new BreakpointAssignment('xl', 0, 'square', 430),
		];
		$originalWidth = 2000;

		// Since both have width 430, only the first one generates a URL due to $processedWidths check
		$this->urlGenerator->expects($this->once())
			->method('generateUrl')
			->with($path, 430, 370)
			->willReturn('url-430x370');

		$result = $this->generator->generate($path, $assignments, $originalWidth, false);

		$this->assertStringContainsString('430px', $result['sizes']);
		$this->assertStringContainsString('430w', $result['srcset']);

		$this->assertEquals('430px', $result['variables']['--img-width-xxl']);
		$this->assertEqualsWithDelta(1.162162, (float) $result['variables']['--img-aspect-xxl'], 0.00001);
		$this->assertEquals('430px', $result['variables']['--img-width-xl']);
		$this->assertEquals('1', $result['variables']['--img-aspect-xl']);
	}
}
