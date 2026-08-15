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

use Symfony\Component\Process\Process;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Exception\VariantDomainException;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\OutputFormat;

final readonly class JpegoptimPostProcessor extends CliPostProcessor
{
    public function supports(OutputFormat $format): bool
    {
        return OutputFormat::Jpeg === $format;
    }

    protected function buildCommand(string $inputPath, string $outputPath): array
    {
        // --stdout leaves the input file untouched and prints the optimized bytes on
        // stdout instead — no separate output file needed for this one.
        return [$this->bin, '--strip-all', '--stdout', $inputPath];
    }

    protected function readResult(Process $process, string $inputPath, string $outputPath): string
    {
        $contents = $process->getOutput();
        if ('' === $contents) {
            throw new VariantDomainException(sprintf('%s produced empty stdout output.', static::class));
        }

        return $contents;
    }
}
