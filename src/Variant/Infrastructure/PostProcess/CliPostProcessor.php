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
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\GeneratedImage;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Port\PostProcessor;

/**
 * Runs an external CLI binary over the already-encoded bytes (metadata stripping / further
 * lossless-ish compression) — never re-decides format or quality, that already happened in
 * ImageManipulator. A run failure throws: GenerateVariantHandler already wraps the whole
 * pipeline and treats any exception as generation failure (fail marker + event), which is
 * the correct behaviour here too, not a silent pass-through of the unoptimized bytes.
 */
abstract readonly class CliPostProcessor implements PostProcessor
{
    public function __construct(
        protected string $bin,
        protected float $timeout = 30.0,
    ) {
    }

    public function process(GeneratedImage $image): GeneratedImage
    {
        $dir = sys_get_temp_dir().'/pgi-postprocess-'.bin2hex(random_bytes(8));
        mkdir($dir);
        $input = $dir.'/input.'.$this->inputExtension($image);
        $output = $dir.'/output.'.$image->format->extension();

        try {
            file_put_contents($input, $this->inputContents($image));

            $process = new Process($this->buildCommand($input, $output), timeout: $this->timeout);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new VariantDomainException(sprintf('%s failed: %s', static::class, $process->getErrorOutput()));
            }

            return $image->withContents($this->readResult($process, $input, $output));
        } finally {
            @unlink($input);
            @unlink($output);
            @rmdir($dir);
        }
    }

    /**
     * @return list<string>
     */
    abstract protected function buildCommand(string $inputPath, string $outputPath): array;

    /**
     * Override together with inputContents() when the binary can't read its own target
     * format back in (e.g. avifenc only encodes *to* AVIF from PNG/JPEG/y4m — it can't
     * re-process an already-AVIF file).
     */
    protected function inputExtension(GeneratedImage $image): string
    {
        return $image->format->extension();
    }

    protected function inputContents(GeneratedImage $image): string
    {
        return $image->contents;
    }

    protected function readResult(Process $process, string $inputPath, string $outputPath): string
    {
        $contents = file_get_contents($outputPath);
        if (false === $contents) {
            throw new VariantDomainException(sprintf('%s produced no output file at "%s".', static::class, $outputPath));
        }

        return $contents;
    }
}
