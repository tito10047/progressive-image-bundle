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

namespace Tito10047\ProgressiveImageBundle\Tests\Variant\Double;

use Tito10047\ProgressiveImageBundle\Variant\Domain\Exception\SourceNotReadable;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\SourceImage;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\SourcePath;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Port\SourceReader;

final class FakeSourceReader implements SourceReader
{
    private function __construct(private readonly SourceImage|\Throwable $result)
    {
    }

    public static function returning(SourceImage $image): self
    {
        return new self($image);
    }

    public static function failingWith(?\Throwable $exception = null): self
    {
        return new self($exception ?? new SourceNotReadable('source unreadable'));
    }

    public function read(SourcePath $path): SourceImage
    {
        if ($this->result instanceof \Throwable) {
            throw $this->result;
        }

        return $this->result;
    }
}
