<?php

/*
 * This file is part of the Progressive Image Bundle.
 *
 * (c) Jozef Môstka <https://github.com/tito10047/progressive-image-bundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tito10047\ProgressiveImageBundle\Tests\Unit\Analyzer;

use PHPUnit\Framework\TestCase;
use Tito10047\ProgressiveImageBundle\Analyzer\GdImageAnalyzer;
use Tito10047\ProgressiveImageBundle\DTO\ImageMetadata;
use Tito10047\ProgressiveImageBundle\Exception\ImageProcessingException;
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

    public function testAnalyzeThrowsWithDiagnosticGdMessageForCorruptData(): void
    {
        $loader = $this->createMock(LoaderInterface::class);
        $path = 'tests/Fixtures/corrupt.bin';

        $stream = fopen('php://memory', 'r+');
        fwrite($stream, 'not an image at all, definitely garbage bytes');
        rewind($stream);
        $loader->expects($this->once())->method('load')->willReturn($stream);

        $analyzer = new GdImageAnalyzer();

        try {
            $analyzer->analyze($loader, $path);
            $this->fail('Expected ImageProcessingException to be thrown.');
        } catch (ImageProcessingException $e) {
            $this->assertStringContainsString($path, $e->getMessage());
            $this->assertStringContainsString('recognized format', $e->getMessage());
        } finally {
            fclose($stream);
        }
    }

    public function testCalculateTargetDimensionsThrowsForZeroHeight(): void
    {
        $this->expectException(ImageProcessingException::class);

        $method = new \ReflectionMethod(GdImageAnalyzer::class, 'calculateTargetDimensions');
        $method->invoke(null, 100, 0);
    }

    public function testCalculateTargetDimensionsThrowsForZeroWidth(): void
    {
        $this->expectException(ImageProcessingException::class);

        $method = new \ReflectionMethod(GdImageAnalyzer::class, 'calculateTargetDimensions');
        $method->invoke(null, 0, 100);
    }

    public function testCalculateTargetDimensionsPreservesAspectRatioForWideImage(): void
    {
        $method = new \ReflectionMethod(GdImageAnalyzer::class, 'calculateTargetDimensions');

        [$width, $height] = $method->invoke(null, 200, 100);

        $this->assertSame(64, $width);
        $this->assertSame(32, $height);
    }

    public function testCalculateTargetDimensionsPreservesAspectRatioForTallImage(): void
    {
        $method = new \ReflectionMethod(GdImageAnalyzer::class, 'calculateTargetDimensions');

        [$width, $height] = $method->invoke(null, 100, 200);

        $this->assertSame(32, $width);
        $this->assertSame(64, $height);
    }

    public function testBlendAlphaLeavesOpaquePixelUnchanged(): void
    {
        $rgba = self::gdColorAt(10, 20, 30, 0);

        $method = new \ReflectionMethod(GdImageAnalyzer::class, 'blendAlpha');
        [$r, $g, $b] = $method->invoke(null, $rgba);

        $this->assertSame([10, 20, 30], [$r, $g, $b]);
    }

    public function testBlendAlphaBlendsFullyTransparentPixelToWhite(): void
    {
        $rgba = self::gdColorAt(10, 20, 30, 127);

        $method = new \ReflectionMethod(GdImageAnalyzer::class, 'blendAlpha');
        [$r, $g, $b] = $method->invoke(null, $rgba);

        $this->assertSame([255, 255, 255], [$r, $g, $b]);
    }

    public function testBlendAlphaBlendsSemiTransparentPixelTowardWhite(): void
    {
        $rgba = self::gdColorAt(0, 0, 0, 64);

        $method = new \ReflectionMethod(GdImageAnalyzer::class, 'blendAlpha');
        [$r, $g, $b] = $method->invoke(null, $rgba);

        $this->assertSame([129, 129, 129], [$r, $g, $b]);
    }

    public function testAnalyzePreservesTransparencyThroughResizeInsteadOfBlackHalo(): void
    {
        $src = imagecreatetruecolor(20, 20);
        imagesavealpha($src, true);
        imagealphablending($src, false);
        $transparent = imagecolorallocatealpha($src, 200, 200, 200, 127);
        imagefill($src, 0, 0, $transparent);
        ob_start();
        imagepng($src);
        $data = ob_get_clean();

        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $data);
        rewind($stream);

        $loader = $this->createMock(LoaderInterface::class);
        $loader->expects($this->once())->method('load')->willReturn($stream);

        $analyzer = new GdImageAnalyzer();
        $metadata = $analyzer->analyze($loader, 'tests/Fixtures/transparent.png');
        fclose($stream);

        // A fully transparent source should blend to white, never to black.
        $decoded = \kornrunner\Blurhash\Blurhash::decode($metadata->originalHash, 4, 4);
        $pixel = $decoded[0][0];
        $this->assertGreaterThan(200, $pixel[0]);
        $this->assertGreaterThan(200, $pixel[1]);
        $this->assertGreaterThan(200, $pixel[2]);
    }

    private static function gdColorAt(int $r, int $g, int $b, int $alpha): int
    {
        $im = imagecreatetruecolor(1, 1);
        imagesavealpha($im, true);
        imagealphablending($im, false);
        $color = imagecolorallocatealpha($im, $r, $g, $b, $alpha);
        imagesetpixel($im, 0, 0, $color);

        return imagecolorat($im, 0, 0);
    }
}
