<?php

namespace Tito10047\ProgressiveImageBundle\Tests\Unit\Twig\Components;

use PHPUnit\Framework\TestCase;
use Tito10047\ProgressiveImageBundle\Decorators\PathDecoratorInterface;
use Tito10047\ProgressiveImageBundle\DTO\ImageMetadata;
use Tito10047\ProgressiveImageBundle\Service\MetadataReader;
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

        $component = new Image($metadataReader, [$decorator]);
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

        $component = new Image($metadataReader, []);
        $component->src = 'test.jpg';
        $component->postMount();

        $this->assertNull($component->getHash());
        $this->assertNull($component->getWidth());
        $this->assertNull($component->getHeight());
        $this->assertSame('test.jpg', $component->getDecoratedSrc());
    }
}
