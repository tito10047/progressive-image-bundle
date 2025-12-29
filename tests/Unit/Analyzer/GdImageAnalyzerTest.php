<?php

namespace Tito10047\ProgressiveImageBundle\Tests\Unit\Analyzer;

use PHPUnit\Framework\TestCase;
use Tito10047\ProgressiveImageBundle\Analyzer\GdImageAnalyzer;
use Tito10047\ProgressiveImageBundle\DTO\ImageMetadata;
use Tito10047\ProgressiveImageBundle\Loader\LoaderInterface;

class GdImageAnalyzerTest extends TestCase
{
    public function testAnalyze(): void
    {
        $loader = $this->createMock(LoaderInterface::class);
        $path = 'tests/Fixtures/test.png';
        
        $stream = fopen($path, 'rb');
        $loader->expects($this->once())
            ->method('load')
            ->with($path)
            ->willReturn($stream);

        $analyzer = new GdImageAnalyzer();
        $metadata = $analyzer->analyze($loader, $path);

        $this->assertInstanceOf(ImageMetadata::class, $metadata);
        $this->assertSame(100, $metadata->width);
        $this->assertSame(100, $metadata->height);
        $this->assertIsString($metadata->originalHash);
        
        fclose($stream);
    }
}
