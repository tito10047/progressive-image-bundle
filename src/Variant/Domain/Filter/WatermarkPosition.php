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

enum WatermarkPosition: string
{
    case TopLeft = 'top_left';
    case TopRight = 'top_right';
    case Top = 'top';
    case BottomLeft = 'bottom_left';
    case BottomRight = 'bottom_right';
    case Bottom = 'bottom';
    case Center = 'center';
}
