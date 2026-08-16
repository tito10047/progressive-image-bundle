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

namespace Tito10047\ProgressiveImageBundle\Variant\Domain\Filter;

/**
 * Rotates the image upright according to its EXIF orientation tag, then discards the tag
 * (the pixels themselves are now correctly oriented, so a viewer no longer needs it).
 */
final readonly class AutoRotate implements Filter
{
    public function canonical(): array
    {
        return ['auto_rotate' => true];
    }
}
