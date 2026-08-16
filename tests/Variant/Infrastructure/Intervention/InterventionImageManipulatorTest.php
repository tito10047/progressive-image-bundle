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

namespace Tito10047\ProgressiveImageBundle\Tests\Variant\Infrastructure\Intervention;

use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use PHPUnit\Framework\TestCase;
use Tito10047\ProgressiveImageBundle\Loader\FileSystemLoader;
use Tito10047\ProgressiveImageBundle\Resolver\FileSystemResolver;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\AutoRotate;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\Background;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\Crop;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\FilterChain;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\Grayscale;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\Negative;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\Paste;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\RelativeResize;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\Resize;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\Rotate;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\Thumbnail;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\Watermark;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\WatermarkPosition;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\CropBox;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\Dimensions;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\OutputFormat;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\Quality;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\SourceImage;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\SourcePath;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\VariantSpec;
use Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Intervention\InterventionImageManipulator;
use Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Source\ResolverChainSourceReader;

final class InterventionImageManipulatorTest extends TestCase
{
    private string $root;
    private ResolverChainSourceReader $sourceReader;
    private InterventionImageManipulator $manipulator;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/pgi-manipulator-'.bin2hex(random_bytes(8));
        mkdir($this->root);

        $watermark = imagecreatetruecolor(10, 10);
        imagepng($watermark, $this->root.'/watermark.png');

        $this->sourceReader = new ResolverChainSourceReader(new FileSystemResolver([$this->root]), new FileSystemLoader());
        $this->manipulator = new InterventionImageManipulator(new ImageManager(Driver::class), $this->sourceReader);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->root.'/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->root);
    }

    /**
     * @param positive-int $width
     * @param positive-int $height
     */
    private function source(int $width, int $height, bool $withAlpha = false): SourceImage
    {
        $image = imagecreatetruecolor($width, $height);
        if ($withAlpha) {
            imagealphablending($image, false);
            imagesavealpha($image, true);
            $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
            self::assertNotFalse($transparent);
            imagefill($image, 0, 0, $transparent);
        }

        $path = $this->root.'/source-'.bin2hex(random_bytes(4)).'.png';
        imagepng($image, $path);

        $stream = fopen($path, 'r');
        self::assertNotFalse($stream);

        return new SourceImage($stream, new Dimensions($width, $height), 'image/png');
    }

    private function spec(FilterChain $filters, OutputFormat $format = OutputFormat::Png, int $quality = 85, bool $progressive = false, bool $stripMetadata = false): VariantSpec
    {
        return new VariantSpec($filters, $format, new Quality($quality), $progressive, $stripMetadata);
    }

    /**
     * SOF2 (0xFFC2) = progressive DCT, SOF0 (0xFFC0) = baseline DCT — a single-scan test
     * image from GD/libjpeg emits exactly one of the two.
     */
    private function isProgressiveJpeg(string $bytes): bool
    {
        return false !== strpos($bytes, "\xFF\xC2") && false === strpos($bytes, "\xFF\xC0");
    }

    /**
     * @return array{int, int}
     */
    private function dimensionsOf(string $contents): array
    {
        $info = getimagesizefromstring($contents);
        self::assertIsArray($info);

        return [$info[0], $info[1]];
    }

    public function testOutboundThumbnailCropsToExactTargetDimensions(): void
    {
        $result = $this->manipulator->process(
            $this->source(400, 200),
            $this->spec(FilterChain::of(Thumbnail::outbound(new Dimensions(100, 100))))
        );

        [$width, $height] = $this->dimensionsOf($result->contents);
        self::assertSame(100, $width);
        self::assertSame(100, $height);
    }

    public function testInsetThumbnailFitsWithinBoundsPreservingAspectRatio(): void
    {
        $result = $this->manipulator->process(
            $this->source(400, 200),
            $this->spec(FilterChain::of(Thumbnail::inset(new Dimensions(100, 100))))
        );

        [$width, $height] = $this->dimensionsOf($result->contents);
        self::assertSame(100, $width, 'the wider dimension must hit the bound exactly');
        self::assertSame(50, $height, 'the aspect ratio (2:1) must be preserved');
    }

    public function testCropProducesTheExactRequestedRegion(): void
    {
        $result = $this->manipulator->process(
            $this->source(400, 200),
            $this->spec(FilterChain::of(new Crop(new CropBox(50, 25, new Dimensions(100, 80)))))
        );

        [$width, $height] = $this->dimensionsOf($result->contents);
        self::assertSame(100, $width);
        self::assertSame(80, $height);
    }

    public function testResizeStretchesToExactDimensions(): void
    {
        $result = $this->manipulator->process(
            $this->source(400, 200),
            $this->spec(FilterChain::of(new Resize(new Dimensions(50, 50))))
        );

        [$width, $height] = $this->dimensionsOf($result->contents);
        self::assertSame(50, $width);
        self::assertSame(50, $height);
    }

    public function testRotateBy90DegreesSwapsWidthAndHeight(): void
    {
        $result = $this->manipulator->process(
            $this->source(400, 200),
            $this->spec(FilterChain::of(new Rotate(90)))
        );

        [$width, $height] = $this->dimensionsOf($result->contents);
        self::assertSame(200, $width);
        self::assertSame(400, $height);
    }

    public function testBackgroundFillsTransparentAreas(): void
    {
        $result = $this->manipulator->process(
            $this->source(20, 20, withAlpha: true),
            $this->spec(FilterChain::of(new Background('#ff0000')))
        );

        $image = imagecreatefromstring($result->contents);
        self::assertNotFalse($image);
        $rgb = imagecolorat($image, 10, 10);
        self::assertSame(255, ($rgb >> 16) & 0xFF, 'red channel should now be opaque red, not transparent');
    }

    public function testWatermarkInsertsAnotherSourceImageWithoutChangingCanvasSize(): void
    {
        $result = $this->manipulator->process(
            $this->source(100, 100),
            $this->spec(FilterChain::of(new Watermark(new SourcePath('watermark.png'), WatermarkPosition::Center)))
        );

        [$width, $height] = $this->dimensionsOf($result->contents);
        self::assertSame(100, $width);
        self::assertSame(100, $height);
    }

    public function testEncodesToTheRequestedFormatAndQuality(): void
    {
        $result = $this->manipulator->process(
            $this->source(20, 20),
            $this->spec(FilterChain::empty(), OutputFormat::Webp, 60)
        );

        self::assertSame(OutputFormat::Webp, $result->format);
        $info = getimagesizefromstring($result->contents);
        self::assertIsArray($info);
        self::assertSame('image/webp', $info['mime']);
    }

    public function testChainsMultipleFiltersInOrder(): void
    {
        $result = $this->manipulator->process(
            $this->source(400, 200),
            $this->spec(FilterChain::of(
                new Crop(new CropBox(0, 0, new Dimensions(200, 200))),
                Thumbnail::outbound(new Dimensions(50, 50))
            ))
        );

        [$width, $height] = $this->dimensionsOf($result->contents);
        self::assertSame(50, $width);
        self::assertSame(50, $height);
    }

    public function testGrayscaleRemovesColorInformation(): void
    {
        $image = imagecreatetruecolor(10, 10);
        imagefill($image, 0, 0, imagecolorallocate($image, 200, 0, 0));
        $path = $this->root.'/red.png';
        imagepng($image, $path);
        $stream = fopen($path, 'r');
        self::assertNotFalse($stream);
        $source = new SourceImage($stream, new Dimensions(10, 10), 'image/png');

        $result = $this->manipulator->process($source, $this->spec(FilterChain::of(new Grayscale())));

        $decoded = imagecreatefromstring($result->contents);
        self::assertNotFalse($decoded);
        $rgb = imagecolorat($decoded, 5, 5);
        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;
        self::assertSame($r, $g, 'grayscale must equalize the channels');
        self::assertSame($g, $b, 'grayscale must equalize the channels');
    }

    public function testNegativeInvertsColors(): void
    {
        $image = imagecreatetruecolor(10, 10);
        imagefill($image, 0, 0, imagecolorallocate($image, 0, 0, 0));
        $path = $this->root.'/black.png';
        imagepng($image, $path);
        $stream = fopen($path, 'r');
        self::assertNotFalse($stream);
        $source = new SourceImage($stream, new Dimensions(10, 10), 'image/png');

        $result = $this->manipulator->process($source, $this->spec(FilterChain::of(new Negative())));

        $decoded = imagecreatefromstring($result->contents);
        self::assertNotFalse($decoded);
        $rgb = imagecolorat($decoded, 5, 5);
        self::assertSame(255, ($rgb >> 16) & 0xFF, 'inverted black must be white');
    }

    public function testAutoRotateAppliesWithoutErrorOnAnImageWithNoExifData(): void
    {
        $result = $this->manipulator->process(
            $this->source(100, 50),
            $this->spec(FilterChain::of(new AutoRotate()))
        );

        [$width, $height] = $this->dimensionsOf($result->contents);
        self::assertSame(100, $width, 'no EXIF orientation present, dimensions must be unchanged');
        self::assertSame(50, $height);
    }

    public function testPasteInsertsAnotherSourceImageAtAnAbsolutePosition(): void
    {
        $result = $this->manipulator->process(
            $this->source(100, 100),
            $this->spec(FilterChain::of(new Paste(new SourcePath('watermark.png'), 20, 30)))
        );

        [$width, $height] = $this->dimensionsOf($result->contents);
        self::assertSame(100, $width, 'paste must not change the canvas size');
        self::assertSame(100, $height);
    }

    public function testRelativeResizeScalesByPercentOfCurrentDimensions(): void
    {
        $result = $this->manipulator->process(
            $this->source(200, 100),
            $this->spec(FilterChain::of(new RelativeResize(widthPercent: 50.0, heightPercent: 50.0)))
        );

        [$width, $height] = $this->dimensionsOf($result->contents);
        self::assertSame(100, $width);
        self::assertSame(50, $height);
    }

    public function testRelativeResizeAppliesAfterAPriorFilterInTheChain(): void
    {
        // Relative to the image's state at that point in the pipeline (100x100, after the
        // thumbnail), not the original source (200x100) — same semantics Liip's own
        // relative_resize has always had.
        $result = $this->manipulator->process(
            $this->source(200, 100),
            $this->spec(FilterChain::of(
                Thumbnail::outbound(new Dimensions(100, 100)),
                new RelativeResize(widthPercent: 50.0, heightPercent: 50.0)
            ))
        );

        [$width, $height] = $this->dimensionsOf($result->contents);
        self::assertSame(50, $width);
        self::assertSame(50, $height);
    }

    public function testProgressiveTrueProducesAProgressiveJpeg(): void
    {
        $result = $this->manipulator->process(
            $this->source(50, 50),
            $this->spec(FilterChain::empty(), OutputFormat::Jpeg, progressive: true)
        );

        self::assertTrue($this->isProgressiveJpeg($result->contents));
    }

    public function testProgressiveFalseProducesABaselineJpeg(): void
    {
        $result = $this->manipulator->process(
            $this->source(50, 50),
            $this->spec(FilterChain::empty(), OutputFormat::Jpeg, progressive: false)
        );

        self::assertFalse($this->isProgressiveJpeg($result->contents));
    }

    public function testProgressiveTrueProducesAnInterlacedPng(): void
    {
        $result = $this->manipulator->process(
            $this->source(50, 50),
            $this->spec(FilterChain::empty(), OutputFormat::Png, progressive: true)
        );

        // PNG IHDR's interlace-method byte sits right after the 8-byte signature + the
        // 4-byte length/4-byte "IHDR" header + 4-byte width + 4-byte height + 3 single-byte
        // fields (bit depth, color type, compression) + 1-byte filter method = offset 28.
        self::assertSame(1, ord($result->contents[28]), 'interlace method must be Adam7 (1)');
    }

    public function testStripMetadataDoesNotBreakEncodingForEachFormat(): void
    {
        foreach ([OutputFormat::Jpeg, OutputFormat::Webp, OutputFormat::Avif] as $format) {
            $result = $this->manipulator->process(
                $this->source(20, 20),
                $this->spec(FilterChain::empty(), $format, stripMetadata: true)
            );

            $info = getimagesizefromstring($result->contents);
            self::assertIsArray($info, "stripMetadata must still produce a valid decodable {$format->value}");
        }
    }
}
