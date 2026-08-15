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

namespace Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Lock;

use Symfony\Component\Lock\SharedLockInterface;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Port\Lock;

final readonly class SymfonyLock implements Lock
{
    public function __construct(public SharedLockInterface $inner)
    {
    }
}
