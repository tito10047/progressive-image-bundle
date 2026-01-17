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
use Tito10047\ProgressiveImageBundle\Modifier\ModifierInterface;
use Tito10047\ProgressiveImageBundle\Modifier\ModifierProvider;
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
		$this->generator = new ResponsiveAttributeGenerator($this->gridConfig, $this->ratioConfig, [1, 2], $this->preloadCollector, $this->urlGenerator);
	}

	public function testGenerateRetina(): void {
		$path          = 'test.jpg';
		$assignments   = [
			new BreakpointAssignment('md', 6, 'landscape'),
		];
		$originalWidth = 2000;

		// md: 6/12 * 720px = 360px.
		// retina: 360 * 1 = 360, 360 * 2 = 720.

		$this->urlGenerator->expects($this->exactly(2))
			->method('generateUrl')
			->willReturnMap([
				[$path, 360, 240, null, [], 'url-360'],
				[$path, 720, 480, null, [], 'url-720'],
			]);

		$result = $this->generator->generate($path, $assignments, $originalWidth, false, null, [], true);

		$this->assertEquals('360px', $result->getSources()[0]->getSizes());
		$this->assertEquals('(min-width: 768px)', $result->getSources()[0]->getMedia());
		$this->assertStringContainsString('url-360 360w', $result->getSources()[0]->getSrcset());
		$this->assertStringContainsString('url-720 720w', $result->getSources()[0]->getSrcset());
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
				[$path, 360, 240, null, [], 'url-360'],
				[$path, 1920, 1920, null, [], 'url-1920'],
            ]);

        $result = $this->generator->generate($path, $assignments, $originalWidth, false);

		$this->assertEquals('360px', $result->getSources()[0]->getSizes());
		$this->assertEquals('(min-width: 768px)', $result->getSources()[0]->getMedia());
		$this->assertStringContainsString('url-360 360w', $result->getSources()[0]->getSrcset());

		$this->assertEquals('100vw', $result->getDefaultSource()->getSizes());
		$this->assertNull($result->getDefaultSource()->getMedia());
		$this->assertStringContainsString('url-1920 1920w', $result->getDefaultSource()->getSrcset());

		$variables = $result->getVariables();
		$this->assertEquals('100vw', $variables['--img-width']);
		$this->assertEquals('1', $variables['--img-aspect']);
		$this->assertEquals('360px', $variables['--img-width-md']);
		$this->assertEquals('1.5', $variables['--img-aspect-md']);
    }

    public function testResolveRatioWithDifferentFormats(): void
    {
        $path = 'test.jpg';
        $originalWidth = 2000;

        // Test format "3/4"
        $assignments1 = [new BreakpointAssignment('md', 6, '3/4')];

        $this->urlGenerator->expects($this->once())
            ->method('generateUrl')
			->with($path, 360, 480, null, [])
            ->willReturn('url');

        $this->generator->generate($path, $assignments1, $originalWidth, false);

        // Test format "16-9"
        $assignments2 = [new BreakpointAssignment('md', 6, '16-9')];
        $this->urlGenerator = $this->createMock(ResponsiveImageUrlGeneratorInterface::class);
		$this->generator = new ResponsiveAttributeGenerator($this->gridConfig, $this->ratioConfig, [1, 2], $this->preloadCollector, $this->urlGenerator);

        $this->urlGenerator->expects($this->once())
            ->method('generateUrl')
			->with($path, 360, (int) round(360 / (16 / 9)), null, [])
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
			->with($path, 360, 240, null, [])
            ->willReturn('url-360');

        $result = $this->generator->generate($path, $assignments, $originalWidth, false);

		$this->assertEquals('url-360 360w', $result->getSources()[0]->getSrcset());
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
			->with($path, 1920, 1920, null, [])
			->willReturn('url-1920');

		$result = $this->generator->generate($path, $assignments, $originalWidth, false);

		$this->assertEquals('100vw', $result->getDefaultSource()->getSizes());
		$this->assertStringContainsString('url-1920 1920w', $result->getDefaultSource()->getSrcset());
		$this->assertEquals('100vw', $result->getVariables()['--img-width']);
	}

	public function testGenerateWithExplicitDimensions(): void {
		$path          = 'test.jpg';
		$assignments   = [
			new BreakpointAssignment('xxl', 0, '430x370', 430, 370),
			new BreakpointAssignment('xl', 0, 'square', 430),
		];
		$originalWidth = 2000;

		// Both have width 430, so both generate a URL
		$this->urlGenerator->expects($this->exactly(2))
			->method('generateUrl')
			->willReturnMap([
				[$path, 430, 370, null, [], 'url-430x370'],
				[$path, 430, 430, null, [], 'url-430x430'],
			]);

		$result = $this->generator->generate($path, $assignments, $originalWidth, false);

		$this->assertStringContainsString('430px', $result->getSources()[0]->getSizes());
		$this->assertStringContainsString('430w', $result->getSources()[0]->getSrcset());

		$variables = $result->getVariables();
		$this->assertEquals('430px', $variables['--img-width-xxl']);
		$this->assertEqualsWithDelta(1.162162, (float) $variables['--img-aspect-xxl'], 0.00001);
		$this->assertEquals('430px', $variables['--img-width-xl']);
		$this->assertEquals('1', $variables['--img-aspect-xl']);
	}

	public function testGenerateWithPercentageWidth(): void {
		$path          = 'test.jpg';
		$assignments   = [
			new BreakpointAssignment('xxl', 0, 'landscape', null, null, '80%'),
		];
		$originalWidth = 2000;

		// xxl: 80% of 1320px = 1056px.
		$this->urlGenerator->expects($this->once())
			->method('generateUrl')
			->with($path, 1056, 704, null, [])
			->willReturn('url-1056');

		$result = $this->generator->generate($path, $assignments, $originalWidth, false);

		$this->assertEquals('(min-width: 1400px) 1056px', $result->getSources()[0]->getMedia() . ' ' . $result->getSources()[0]->getSizes());
		$this->assertStringContainsString('url-1056 1056w', $result->getSources()[0]->getSrcset());
		$this->assertEquals('80%', $result->getVariables()['--img-width-xxl']);
	}

	public function testGenerateWithNewRatioFormats(): void {
		$path          = 'test.jpg';
		$originalWidth = 2000;

		// Test decimal ratio: sm:[100%]@[0.65]
		// sm max_container is 540. 100% of 540 = 540.
		// ratio 0.65 -> 540 / 0.65 = 830.76... -> 831
		$assignments1 = [BreakpointAssignment::fromSegment('sm:[100%]@[0.65]', null)];
		$this->urlGenerator->expects($this->once())
			->method('generateUrl')
			->with($path, 540, 831, null, [])
			->willReturn('url-decimal');

		$result1 = $this->generator->generate($path, $assignments1, $originalWidth, false);
		$this->assertEquals('0.65', $result1->getVariables()['--img-aspect-sm']);

		// Test fraction ratio: [100%]@[10/9]
		// default/xs max_container is null -> 100% of 1920 = 1920.
		// ratio 10/9 = 1.111... -> 1920 / 1.111... = 1728
		$this->urlGenerator = $this->createMock(ResponsiveImageUrlGeneratorInterface::class);
		$this->generator    = new ResponsiveAttributeGenerator($this->gridConfig, $this->ratioConfig, [1, 2], $this->preloadCollector, $this->urlGenerator);
		$assignments2       = [BreakpointAssignment::fromSegment('[100%]@[10/9]', null)];
		$this->urlGenerator->expects($this->once())
			->method('generateUrl')
			->with($path, 1920, 1728, null, [])
			->willReturn('url-fraction');

		$result2 = $this->generator->generate($path, $assignments2, $originalWidth, false);
		$this->assertEquals('1.1111111111111', substr($result2->getVariables()['--img-aspect'], 0, 15));

		// Test dimensions ratio: [100%]@[1500x700]
		// ratio 1500/700 = 2.1428... -> 1920 / 2.1428... = 896
		$this->urlGenerator = $this->createMock(ResponsiveImageUrlGeneratorInterface::class);
		$this->generator    = new ResponsiveAttributeGenerator($this->gridConfig, $this->ratioConfig, [1, 2], $this->preloadCollector, $this->urlGenerator);
		$assignments3       = [BreakpointAssignment::fromSegment('[100%]@[1500x700]', null)];
		$this->urlGenerator->expects($this->once())
			->method('generateUrl')
			->with($path, 1920, 896, null, [])
			->willReturn('url-dimensions');

		$result3 = $this->generator->generate($path, $assignments3, $originalWidth, false);
		$this->assertEqualsWithDelta(2.142857, (float) $result3->getVariables()['--img-aspect'], 0.00001);
	}

	public function testGenerateWithModifiers(): void {
		$path          = 'test.jpg';
		$assignments   = [
			new BreakpointAssignment('md', 6, 'landscape', null, null, null, ['circle']),
		];
		$originalWidth = 2000;

		$modifier = $this->createMock(ModifierInterface::class);
		$modifier->method('supports')->with('circle')->willReturn(true);
		$modifier->method('modify')->with('circle', [])->willReturn(['circle' => true]);

		$modifierProvider = new ModifierProvider([$modifier]);
		$generator        = new ResponsiveAttributeGenerator($this->gridConfig, $this->ratioConfig, [1], $this->preloadCollector, $this->urlGenerator, $modifierProvider);

		$this->urlGenerator->expects($this->once())
			->method('generateUrl')
			->with($path, 360, 240, null, ['circle' => true])
			->willReturn('url-circle');

		$result = $generator->generate($path, $assignments, $originalWidth, false);

		$this->assertStringContainsString('url-circle 360w', $result->getSources()[0]->getSrcset());
	}
}
