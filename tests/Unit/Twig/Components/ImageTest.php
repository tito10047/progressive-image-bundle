<?php

namespace Tito10047\ProgressiveImageBundle\Tests\Unit\Twig\Components;

use PHPUnit\Framework\TestCase;
use Tito10047\ProgressiveImageBundle\DTO\ImageMetadata;
use Tito10047\ProgressiveImageBundle\Service\MetadataReader;
use Tito10047\ProgressiveImageBundle\Service\PreloadCollector;
use Tito10047\ProgressiveImageBundle\SrcsetGenerator\SrcsetGeneratorInterface;
use Tito10047\ProgressiveImageBundle\Twig\Components\Image;

class ImageTest extends TestCase
{
    public function testGetRawSrcSetIncludesOriginalWidth(): void
    {
        $analyzer = $this->createMock(MetadataReader::class);
        $srcsetGenerator = $this->createMock(SrcsetGeneratorInterface::class);
        $preloadCollector = $this->createMock(PreloadCollector::class);
        
        $metadata = new ImageMetadata('hash', 1000, 500);
        $analyzer->method('getMetadata')->willReturn($metadata);

        $breakpoints = ['mobile' => 320, 'desktop' => 1024];
        $defaultPreset = [
            'widths' => ['mobile', 'desktop'],
            'sizes' => '100vw'
        ];

        $srcsetGenerator->method('generate')->willReturn([
            'mobile' => 'path/to/mobile.jpg',
            'desktop' => 'path/to/desktop.jpg',
            'original' => 'path/to/original.jpg',
        ]);

        $image = new Image(
            $analyzer,
            [],
            $preloadCollector,
            $srcsetGenerator,
            $breakpoints,
            $defaultPreset,
            []
        );
        $image->src = 'test.jpg';
        $image->postMount();

        $srcset = $image->getRawSrcSet();

        $this->assertStringContainsString('path/to/mobile.jpg 320w', $srcset);
        $this->assertStringContainsString('path/to/desktop.jpg 1024w', $srcset);
        $this->assertStringContainsString('path/to/original.jpg 1000w', $srcset);
    }
}
