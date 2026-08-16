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

namespace Tito10047\ProgressiveImageBundle\Variant\Domain\Event;

use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\VariantId;

final readonly class VariantRequested
{
    public function __construct(public VariantId $id)
    {
    }
}
