<?php

namespace Tito10047\ProgressiveImageBundle\Tests\Unit\Analyzer;

use PHPUnit\Framework\TestCase;
use Tito10047\ProgressiveImageBundle\Analyzer\ImagickAnalyzer;
use Tito10047\ProgressiveImageBundle\DTO\ImageMetadata;
use Tito10047\ProgressiveImageBundle\Loader\LoaderInterface;

class ImagickAnalyzerTest extends TestCase
{
    protected function setUp(): void
    {
        if (!extension_loaded('imagick')) {
            $this->markTestSkipped('Imagick extension is not available.');
        }
    }

    public function testAnalyze(): void
    {
        $loader = $this->createMock(LoaderInterface::class);
        $path = 'tests/Fixtures/test.png';
        
        $stream = fopen($path, 'rb');
        $loader->expects($this->once())
            ->method('load')
            ->with($path)
            ->willReturn($stream);

        $analyzer = new ImagickAnalyzer();
        $metadata = $analyzer->analyze($loader, $path);

        $this->assertInstanceOf(ImageMetadata::class, $metadata);
        $this->assertGreaterThanOrEqual(60, $metadata->width);
        $this->assertGreaterThanOrEqual(60, $metadata->height);
        $this->assertIsString($metadata->originalHash);
        
        fclose($stream);
    }
}
