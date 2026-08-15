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

namespace Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Intervention;

use Intervention\Image\Alignment;
use Intervention\Image\Encoders\AvifEncoder;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\EncoderInterface;
use Intervention\Image\Interfaces\ImageInterface;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Exception\InvalidFilterDefinition;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\Background;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\Crop;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\Filter;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\Resize;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\Rotate;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\Thumbnail;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\ThumbnailMode;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\Watermark;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\WatermarkPosition;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\GeneratedImage;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\OutputFormat;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\Quality;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\SourceImage;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\VariantSpec;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Port\ImageManipulator;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Port\SourceReader;

/**
 * The single point of contact with intervention/image. The driver (gd/imagick) is
 * whatever $imageManager was built with — a DI-wiring concern (D4), not this class's.
 * A Watermark filter needs to load a second Source (the mark image), so this adapter
 * also depends on SourceReader — composing one Infrastructure adapter with a Domain port
 * is normal; it does not reach back into Application or break the layering.
 */
final readonly class InterventionImageManipulator implements ImageManipulator
{
    public function __construct(
        private ImageManager $imageManager,
        private SourceReader $sourceReader,
    ) {
    }

    public function process(SourceImage $source, VariantSpec $spec): GeneratedImage
    {
        $image = $this->imageManager->decodeStream($source->stream);

        foreach ($spec->filters as $filter) {
            $image = $this->apply($image, $filter);
        }

        return new GeneratedImage($image->encode($this->encoderFor($spec->format, $spec->quality))->toString(), $spec->format);
    }

    private function apply(ImageInterface $image, Filter $filter): ImageInterface
    {
        return match (true) {
            $filter instanceof Thumbnail => $this->thumbnail($image, $filter),
            $filter instanceof Crop => $image->crop($filter->box->size->width, $filter->box->size->height, $filter->box->startX, $filter->box->startY),
            $filter instanceof Resize => $image->resize($filter->size->width, $filter->size->height),
            $filter instanceof Rotate => $image->rotate((float) $filter->degrees),
            $filter instanceof Background => $image->fillTransparentAreas($filter->color),
            $filter instanceof Watermark => $this->watermark($image, $filter),
            default => throw new InvalidFilterDefinition(sprintf('No Intervention mapping for filter "%s".', $filter::class)),
        };
    }

    private function thumbnail(ImageInterface $image, Thumbnail $filter): ImageInterface
    {
        return match ($filter->mode) {
            ThumbnailMode::Outbound => $image->cover($filter->size->width, $filter->size->height),
            ThumbnailMode::Inset => $image->scale($filter->size->width, $filter->size->height),
        };
    }

    private function watermark(ImageInterface $image, Watermark $filter): ImageInterface
    {
        $mark = $this->sourceReader->read($filter->image);
        $markImage = $this->imageManager->decodeStream($mark->stream);

        return $image->insert($markImage, alignment: $this->alignmentFor($filter->position), transparency: $filter->opacity / 100);
    }

    private function alignmentFor(WatermarkPosition $position): Alignment
    {
        return match ($position) {
            WatermarkPosition::TopLeft => Alignment::TOP_LEFT,
            WatermarkPosition::TopRight => Alignment::TOP_RIGHT,
            WatermarkPosition::Top => Alignment::TOP,
            WatermarkPosition::BottomLeft => Alignment::BOTTOM_LEFT,
            WatermarkPosition::BottomRight => Alignment::BOTTOM_RIGHT,
            WatermarkPosition::Bottom => Alignment::BOTTOM,
            WatermarkPosition::Center => Alignment::CENTER,
        };
    }

    private function encoderFor(OutputFormat $format, Quality $quality): EncoderInterface
    {
        return match ($format) {
            OutputFormat::Jpeg => new JpegEncoder(quality: $quality->value),
            OutputFormat::Png => new PngEncoder(),
            OutputFormat::Webp => new WebpEncoder(quality: $quality->value),
            OutputFormat::Avif => new AvifEncoder(quality: $quality->value),
        };
    }
}
