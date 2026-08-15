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
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\Background;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\Crop;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\FilterChain;
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

    private function spec(FilterChain $filters, OutputFormat $format = OutputFormat::Png, int $quality = 85): VariantSpec
    {
        return new VariantSpec($filters, $format, new Quality($quality));
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
}
