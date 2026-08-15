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
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\OutputFormat;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Port\PostProcessor;

final class FakePostProcessor implements PostProcessor
{
    public function __construct(private readonly OutputFormat $supportedFormat, private readonly string $marker)
    {
    }

    public function supports(OutputFormat $format): bool
    {
        return $format === $this->supportedFormat;
    }

    public function process(GeneratedImage $image): GeneratedImage
    {
        return $image->withContents($image->contents.'|'.$this->marker);
    }
}
