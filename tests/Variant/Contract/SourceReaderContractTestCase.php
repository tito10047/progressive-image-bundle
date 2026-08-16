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

namespace Tito10047\ProgressiveImageBundle\Tests\Variant\Contract;

use PHPUnit\Framework\TestCase;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Exception\SourceNotReadable;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\Dimensions;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\SourcePath;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Port\SourceReader;

/**
 * One suite, run against every SourceReader implementation (local filesystem, HTTP, ...) —
 * a reader that passes ad-hoc tests but not this shared contract is a lie the rest of the
 * pipeline (GenerateVariantHandler, InterventionImageManipulator) silently relies on.
 */
abstract class SourceReaderContractTestCase extends TestCase
{
    abstract protected function createReader(): SourceReader;

    abstract protected function existingSourcePath(): SourcePath;

    abstract protected function expectedDimensions(): Dimensions;

    abstract protected function expectedMime(): string;

    abstract protected function missingSourcePath(): SourcePath;

    public function testReadReturnsCorrectDimensionsAndMimeForAnExistingSource(): void
    {
        // The reader must stay referenced for as long as $image->stream is used: some
        // adapters (e.g. FileSystemLoader) close their stream in __destruct(), so an inline
        // $this->createReader()->read(...) would let the reader be garbage-collected —
        // and its stream closed — before this method's assertions run.
        $reader = $this->createReader();
        $image = $reader->read($this->existingSourcePath());

        self::assertEquals($this->expectedDimensions(), $image->dimensions);
        self::assertSame($this->expectedMime(), $image->mime);
        self::assertNotFalse(stream_get_contents($image->stream));
    }

    public function testReadThrowsSourceNotReadableForAMissingSource(): void
    {
        $this->expectException(SourceNotReadable::class);

        $this->createReader()->read($this->missingSourcePath());
    }
}
