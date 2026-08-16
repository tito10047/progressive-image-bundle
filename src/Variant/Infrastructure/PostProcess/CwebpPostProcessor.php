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

use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\OutputFormat;

final readonly class CwebpPostProcessor extends CliPostProcessor
{
    public function __construct(
        string $bin,
        private int $quality = 82,
        float $timeout = 30.0,
    ) {
        parent::__construct($bin, $timeout);
    }

    public function supports(OutputFormat $format): bool
    {
        return OutputFormat::Webp === $format;
    }

    protected function buildCommand(string $inputPath, string $outputPath): array
    {
        return [$this->bin, '-quiet', '-q', (string) $this->quality, $inputPath, '-o', $outputPath];
    }
}
