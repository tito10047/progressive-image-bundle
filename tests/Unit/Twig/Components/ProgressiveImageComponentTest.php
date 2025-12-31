<?php

namespace Tito10047\ProgressiveImageBundle\Tests\Unit\Twig\Components;

use PHPUnit\Framework\TestCase;
use Tito10047\ProgressiveImageBundle\Decorators\PathDecoratorInterface;
use Tito10047\ProgressiveImageBundle\DTO\ImageMetadata;
use Tito10047\ProgressiveImageBundle\SrcsetGenerator\SrcsetGeneratorInterface;
use Tito10047\ProgressiveImageBundle\Service\MetadataReader;
use Tito10047\ProgressiveImageBundle\Service\PreloadCollector;
use Tito10047\ProgressiveImageBundle\Twig\Components\Image;

class ProgressiveImageComponentTest extends TestCase
{
    public function testComponentProperties(): void
    {
        $metadataReader = $this->createMock(MetadataReader::class);
        $metadata = new ImageMetadata('hash', 800, 600);
        
        $metadataReader->expects($this->once())
            ->method('getMetadata')
            ->with('test.jpg')
            ->willReturn($metadata);

        $decorator = $this->createMock(PathDecoratorInterface::class);
        $decorator->expects($this->once())
            ->method('decorate')
            ->with('test.jpg')
            ->willReturn('decorated-test.jpg');
		$collector = $this->createMock(PreloadCollector::class);

        $component = new Image($metadataReader, [$decorator],$collector, null, null, null, null);
        $component->src = 'test.jpg';
        $component->postMount();

        $this->assertSame('hash', $component->getHash());
        $this->assertSame(800, $component->getWidth());
        $this->assertSame(600, $component->getHeight());
        $this->assertSame('decorated-test.jpg', $component->getDecoratedSrc());
    }

    public function testComponentWithNoMetadata(): void
    {
        $metadataReader = $this->createMock(MetadataReader::class);
        $metadataReader->expects($this->once())
            ->method('getMetadata')
            ->willReturn(null);
		$collector = $this->createMock(PreloadCollector::class);

        $component = new Image($metadataReader, [],$collector, null, null, null, null);
        $component->src = 'test.jpg';
        $component->postMount();

        $this->assertNull($component->getHash());
        $this->assertNull($component->getWidth());
        $this->assertNull($component->getHeight());
        $this->assertSame('test.jpg', $component->getDecoratedSrc());
    }

    public function testSrcSetAndSizes(): void
    {
        $metadataReader = $this->createMock(MetadataReader::class);
        $collector = $this->createMock(PreloadCollector::class);
        $srcsetGenerator = $this->createMock(SrcsetGeneratorInterface::class);

        $breakpoints = ['sm' => 480, 'md' => 800];
        $defaultPreset = ['widths' => ['sm', 'md'], 'sizes' => '100vw'];
        $presets = [
            'hero' => ['widths' => ['md'], 'sizes' => '50vw']
        ];

        $srcsetGenerator->expects($this->any())
            ->method('generate')
            ->willReturnCallback(function($path, $breakpoints) {
                if (count($breakpoints) === 2) {
                    return ['sm' => 'sm.jpg', 'md' => 'md.jpg'];
                }
                return ['md' => 'hero-md.jpg'];
            });

        $component = new Image($metadataReader, [], $collector, $srcsetGenerator, $breakpoints, $defaultPreset, $presets);
        $component->src = 'test.jpg';
        $component->postMount();

        $this->assertStringContainsString('srcset="', $component->getSrcSet());
        $this->assertStringContainsString('sm.jpg 480w', $component->getSrcSet());
        $this->assertStringContainsString('md.jpg 800w', $component->getSrcSet());
        $this->assertSame('sizes="100vw"', $component->getSizes());

        // Test with preset
        $component->preset = 'hero';
        $component->postMount();
        
        $this->assertStringContainsString('hero-md.jpg 800w', $component->getSrcSet());
        $this->assertSame('sizes="50vw"', $component->getSizes());
    }
}
