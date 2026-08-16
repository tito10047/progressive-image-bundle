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

namespace Tito10047\ProgressiveImageBundle\Variant\Infrastructure\PostProcess;

use Tito10047\ProgressiveImageBundle\Variant\Domain\Exception\VariantDomainException;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\GeneratedImage;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\OutputFormat;

/**
 * avifenc only *encodes to* AVIF from PNG/JPEG/y4m — unlike jpegoptim/pngquant/cwebp, it
 * cannot read an already-AVIF file back in to re-optimize it. So this decodes the AVIF
 * bytes ImageManipulator already produced back to a lossless PNG intermediate and lets
 * avifenc do the real encoding — the PNG round-trip is the price of using avifenc's own
 * encoder (often better tuned than Intervention's) instead of skipping this processor.
 */
final readonly class AvifencPostProcessor extends CliPostProcessor
{
    public function __construct(
        string $bin,
        private int $quality = 60,
        float $timeout = 30.0,
    ) {
        parent::__construct($bin, $timeout);
    }

    public function supports(OutputFormat $format): bool
    {
        return OutputFormat::Avif === $format;
    }

    protected function buildCommand(string $inputPath, string $outputPath): array
    {
        return [$this->bin, '-q', (string) $this->quality, $inputPath, $outputPath];
    }

    protected function inputExtension(GeneratedImage $image): string
    {
        return 'png';
    }

    protected function inputContents(GeneratedImage $image): string
    {
        $decoded = @imagecreatefromstring($image->contents);
        if (false === $decoded) {
            throw new VariantDomainException('Could not decode AVIF bytes for avifenc re-encoding.');
        }

        ob_start();
        imagepng($decoded);
        $png = ob_get_clean();
        if (false === $png) {
            throw new VariantDomainException('Could not re-encode decoded AVIF image as PNG.');
        }

        return $png;
    }
}
