<?php

namespace Tito10047\ProgressiveImageBundle\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Tito10047\ProgressiveImageBundle\DTO\BreakpointAssignment;
use Tito10047\ProgressiveImageBundle\Service\ResponsiveAttributeGenerator;
use Tito10047\ProgressiveImageBundle\UrlGenerator\ResponsiveImageUrlGeneratorInterface;

class ResponsiveAttributeGeneratorTest extends TestCase
{
    private array $gridConfig;
    private array $ratioConfig;
    private $urlGenerator;
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
        $this->generator = new ResponsiveAttributeGenerator($this->gridConfig, $this->ratioConfig, $this->urlGenerator);
    }

    public function testGenerateBasic(): void
    {
        $path = 'test.jpg';
        $assignments = [
            new BreakpointAssignment('xs', 12, 'square'),
            new BreakpointAssignment('md', 6, 'landscape'),
        ];
        $originalWidth = 2000;

        // xs: 12/12 * 100vw = 100vw. Pixel width (estimate) = 1920px. 1x=1920, 2x=3840 (skipped > 2000)
        // md: 6/12 * 720px = 360px. 1x=360, 2x=720

        $this->urlGenerator->expects($this->exactly(3))
            ->method('generateUrl')
            ->willReturnMap([
                [$path, 360, 240, 'url-360'],
                [$path, 720, 480, 'url-720'],
                [$path, 1920, 1920, 'url-1920'],
            ]);

        $result = $this->generator->generate($path, $assignments, $originalWidth);

        $this->assertEquals('(min-width: 768px) 360px, 100vw', $result['sizes']);
        $this->assertStringContainsString('url-360 360w', $result['srcset']);
        $this->assertStringContainsString('url-720 720w', $result['srcset']);
        $this->assertStringContainsString('url-1920 1920w', $result['srcset']);
    }

    public function testResolveRatioWithDifferentFormats(): void
    {
        $path = 'test.jpg';
        $originalWidth = 2000;

        // Test format "3/4"
        $assignments1 = [new BreakpointAssignment('md', 6, '3/4')];
        
        $matcher = $this->exactly(2);
        $this->urlGenerator->expects($matcher)
            ->method('generateUrl')
            ->willReturnCallback(function (string $path, int $targetW, ?int $targetH) use ($matcher) {
                match ($matcher->numberOfInvocations()) {
                    1 => $this->assertEquals([360, 480], [$targetW, $targetH]),
                    2 => $this->assertEquals([720, 960], [$targetW, $targetH]),
                    default => throw new \Exception('Unexpected invocation'),
                };
                return 'url';
            });
        
        $this->generator->generate($path, $assignments1, $originalWidth);

        // Test format "16-9"
        $assignments2 = [new BreakpointAssignment('md', 6, '16-9')];
        $this->urlGenerator = $this->createMock(ResponsiveImageUrlGeneratorInterface::class);
        $this->generator = new ResponsiveAttributeGenerator($this->gridConfig, $this->ratioConfig, $this->urlGenerator);
        
        $matcher2 = $this->exactly(2);
        $this->urlGenerator->expects($matcher2)
            ->method('generateUrl')
            ->willReturnCallback(function (string $path, int $targetW, ?int $targetH) use ($matcher2) {
                match ($matcher2->numberOfInvocations()) {
                    1 => $this->assertEquals([360, (int)round(360 / (16/9))], [$targetW, $targetH]),
                    2 => $this->assertEquals([720, (int)round(720 / (16/9))], [$targetW, $targetH]),
                    default => throw new \Exception('Unexpected invocation'),
                };
                return 'url';
            });
            
        $this->generator->generate($path, $assignments2, $originalWidth);
    }

    public function testUpscalingProtection(): void
    {
        $path = 'test.jpg';
        $assignments = [
            new BreakpointAssignment('md', 6, 'landscape'),
        ];
        $originalWidth = 500;

        // md: 6/12 * 720px = 360px. 1x=360, 2x=720 (skipped > 500)

        $this->urlGenerator->expects($this->once())
            ->method('generateUrl')
            ->with($path, 360, 240)
            ->willReturn('url-360');

        $result = $this->generator->generate($path, $assignments, $originalWidth);

        $this->assertEquals('url-360 360w', $result['srcset']);
    }
}
