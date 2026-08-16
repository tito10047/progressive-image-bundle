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

namespace Tito10047\ProgressiveImageBundle\Tests\Variant\Application\Service;

use PHPUnit\Framework\TestCase;
use Tito10047\ProgressiveImageBundle\Variant\Application\Service\FilterFactory;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Exception\InvalidFilterDefinition;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\AutoRotate;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\Background;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\Crop;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\Grayscale;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\Negative;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\Paste;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\RelativeResize;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\Resize;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\Rotate;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\Thumbnail;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\Watermark;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\WatermarkPosition;

final class FilterFactoryTest extends TestCase
{
    private FilterFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new FilterFactory();
    }

    public function testCreatesOutboundThumbnailByDefault(): void
    {
        $filter = $this->factory->create('thumbnail', ['size' => [200, 200]]);

        self::assertInstanceOf(Thumbnail::class, $filter);
        self::assertSame(['thumbnail' => ['w' => 200, 'h' => 200, 'mode' => 'outbound']], $filter->canonical());
    }

    public function testCreatesInsetThumbnailWhenModeGiven(): void
    {
        $filter = $this->factory->create('thumbnail', ['size' => [200, 100], 'mode' => 'inset']);

        self::assertSame(['thumbnail' => ['w' => 200, 'h' => 100, 'mode' => 'inset']], $filter->canonical());
    }

    public function testCreatesCropWithStartAndSize(): void
    {
        $filter = $this->factory->create('crop', ['start' => [10, 20], 'size' => [100, 50]]);

        self::assertInstanceOf(Crop::class, $filter);
        self::assertSame(['crop' => ['x' => 10, 'y' => 20, 'w' => 100, 'h' => 50]], $filter->canonical());
    }

    public function testCropDefaultsStartToOrigin(): void
    {
        $filter = $this->factory->create('crop', ['size' => [100, 50]]);

        self::assertSame(['crop' => ['x' => 0, 'y' => 0, 'w' => 100, 'h' => 50]], $filter->canonical());
    }

    public function testCreatesResize(): void
    {
        $filter = $this->factory->create('resize', ['size' => [640, 480]]);

        self::assertInstanceOf(Resize::class, $filter);
        self::assertSame(['resize' => ['w' => 640, 'h' => 480]], $filter->canonical());
    }

    public function testCreatesRotate(): void
    {
        $filter = $this->factory->create('rotate', ['angle' => 90]);

        self::assertInstanceOf(Rotate::class, $filter);
        self::assertSame(['rotate' => ['degrees' => 90]], $filter->canonical());
    }

    public function testCreatesBackground(): void
    {
        $filter = $this->factory->create('background', ['color' => '#FFFFFF']);

        self::assertInstanceOf(Background::class, $filter);
        self::assertSame(['background' => ['color' => '#ffffff']], $filter->canonical());
    }

    public function testCreatesWatermarkWithDefaults(): void
    {
        $filter = $this->factory->create('watermark', ['image' => 'logos/brand.png']);

        self::assertInstanceOf(Watermark::class, $filter);
        self::assertSame($filter->position, WatermarkPosition::Center);
        self::assertSame(100, $filter->opacity);
    }

    public function testCreatesWatermarkWithExplicitOptions(): void
    {
        $filter = $this->factory->create('watermark', ['image' => 'logos/brand.png', 'position' => 'bottom_right', 'opacity' => 40]);

        self::assertInstanceOf(Watermark::class, $filter);
        self::assertSame(WatermarkPosition::BottomRight, $filter->position);
        self::assertSame(40, $filter->opacity);
    }

    public function testThrowsOnUnknownFilterName(): void
    {
        $this->expectException(InvalidFilterDefinition::class);

        $this->factory->create('sepia', []);
    }

    public function testThrowsWhenRequiredSizeOptionIsMissing(): void
    {
        $this->expectException(InvalidFilterDefinition::class);

        $this->factory->create('thumbnail', []);
    }

    public function testThrowsWhenSizeOptionIsNotAPair(): void
    {
        $this->expectException(InvalidFilterDefinition::class);

        $this->factory->create('resize', ['size' => [640]]);
    }

    public function testThrowsOnUnknownThumbnailMode(): void
    {
        $this->expectException(InvalidFilterDefinition::class);

        $this->factory->create('thumbnail', ['size' => [200, 200], 'mode' => 'sideways']);
    }

    public function testCropStartAsAssociativeArrayIsReadByKeyNotByStorageOrder(): void
    {
        // y is stored BEFORE x — array_values() would silently swap them.
        $filter = $this->factory->create('crop', ['start' => ['y' => 20, 'x' => 10], 'size' => [100, 50]]);

        self::assertSame(['crop' => ['x' => 10, 'y' => 20, 'w' => 100, 'h' => 50]], $filter->canonical());
    }

    public function testDimensionsAsAssociativeArrayIsReadByKeyNotByStorageOrder(): void
    {
        // height is stored BEFORE width — array_values() would silently swap them.
        $filter = $this->factory->create('resize', ['size' => ['height' => 480, 'width' => 640]]);

        self::assertSame(['resize' => ['w' => 640, 'h' => 480]], $filter->canonical());
    }

    public function testCreatesGrayscale(): void
    {
        self::assertInstanceOf(Grayscale::class, $this->factory->create('grayscale', []));
    }

    public function testCreatesNegative(): void
    {
        self::assertInstanceOf(Negative::class, $this->factory->create('negative', []));
    }

    public function testCreatesAutoRotate(): void
    {
        self::assertInstanceOf(AutoRotate::class, $this->factory->create('auto_rotate', []));
    }

    public function testCreatesPasteWithDefaults(): void
    {
        $filter = $this->factory->create('paste', ['image' => 'badge.png']);

        self::assertInstanceOf(Paste::class, $filter);
        self::assertSame(['paste' => ['image' => 'badge.png', 'x' => 0, 'y' => 0]], $filter->canonical());
    }

    public function testCreatesPasteWithExplicitPosition(): void
    {
        $filter = $this->factory->create('paste', ['image' => 'badge.png', 'x' => 20, 'y' => 30]);

        self::assertSame(['paste' => ['image' => 'badge.png', 'x' => 20, 'y' => 30]], $filter->canonical());
    }

    public function testThrowsWhenPasteImageIsMissing(): void
    {
        $this->expectException(InvalidFilterDefinition::class);

        $this->factory->create('paste', []);
    }

    public function testCreatesRelativeResize(): void
    {
        $filter = $this->factory->create('relative_resize', ['width_percent' => 50, 'height_percent' => 150]);

        self::assertInstanceOf(RelativeResize::class, $filter);
        self::assertSame(['relative_resize' => ['width_percent' => 50.0, 'height_percent' => 150.0]], $filter->canonical());
    }

    public function testThrowsWhenRelativeResizeHasNeitherDimension(): void
    {
        $this->expectException(InvalidFilterDefinition::class);

        $this->factory->create('relative_resize', []);
    }

    public function testThrowsWhenRelativeResizePercentIsNotNumeric(): void
    {
        $this->expectException(InvalidFilterDefinition::class);

        $this->factory->create('relative_resize', ['width_percent' => 'huge']);
    }
}
