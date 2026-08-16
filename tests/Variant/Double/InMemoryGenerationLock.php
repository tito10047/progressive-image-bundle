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

use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\VariantId;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Port\GenerationLock;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Port\Lock;

final class InMemoryGenerationLock implements GenerationLock
{
    /** @var array<string, bool> */
    private array $held = [];

    private bool $alwaysBusy = false;

    public function acquire(VariantId $id): ?Lock
    {
        if ($this->alwaysBusy || ($this->held[$id->value] ?? false)) {
            return null;
        }

        $this->held[$id->value] = true;

        return new InMemoryLock($id);
    }

    public function release(Lock $lock): void
    {
        if ($lock instanceof InMemoryLock) {
            unset($this->held[$lock->id->value]);
        }
    }

    public function isHeld(VariantId $id): bool
    {
        return $this->held[$id->value] ?? false;
    }

    public function markAlwaysBusy(): void
    {
        $this->alwaysBusy = true;
    }
}
