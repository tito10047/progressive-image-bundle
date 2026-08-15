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

use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\GeneratedImage;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\SourceImage;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\VariantSpec;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Port\ImageManipulator;

final class FakeImageManipulator implements ImageManipulator
{
    private ?\Throwable $throws = null;

    public function process(SourceImage $source, VariantSpec $spec): GeneratedImage
    {
        if (null !== $this->throws) {
            throw $this->throws;
        }

        return new GeneratedImage('processed-image-bytes', $spec->format);
    }

    public function throwOnProcess(\Throwable $exception): void
    {
        $this->throws = $exception;
    }
}
