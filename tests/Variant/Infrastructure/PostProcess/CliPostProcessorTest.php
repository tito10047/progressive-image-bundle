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

namespace Tito10047\ProgressiveImageBundle\Tests\Variant\Infrastructure\PostProcess;

use PHPUnit\Framework\TestCase;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Exception\VariantDomainException;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\GeneratedImage;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\OutputFormat;
use Tito10047\ProgressiveImageBundle\Variant\Infrastructure\PostProcess\CliPostProcessor;

final class CliPostProcessorTest extends TestCase
{
    /**
     * mkdir()'s and file_put_contents()'s return values are checked and turned into a
     * VariantDomainException on failure (see process()) — not exercised here as a genuine
     * failure, since sys_get_temp_dir() is resolved once and cached internally per PHP
     * process, so neither TMPDIR env overrides nor ini_set('sys_temp_dir', ...) can force a
     * failure reliably from within a running test suite without risking the shared real
     * system temp directory. Verified by inspection instead: both calls are now `if (false
     * === ...)`/`if (!...)` guarded and throw, matching the existing pattern used
     * everywhere else in this class (e.g. readResult() below).
     */
    public function testThrowsWhenTheCommandProducesAnEmptyOutputFile(): void
    {
        $processor = new readonly class('true') extends CliPostProcessor {
            public function supports(OutputFormat $format): bool
            {
                return true;
            }

            protected function buildCommand(string $inputPath, string $outputPath): array
            {
                // Succeeds, but deliberately leaves an empty output file behind — the
                // real-world scenario is an external tool that exits 0 after a disk
                // problem or interrupted write.
                return ['touch', $outputPath];
            }
        };

        $this->expectException(VariantDomainException::class);
        $processor->process(new GeneratedImage('data', OutputFormat::Jpeg));
    }
}
