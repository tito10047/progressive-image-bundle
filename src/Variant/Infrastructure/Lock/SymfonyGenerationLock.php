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

use Symfony\Component\Lock\LockFactory;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\VariantId;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Port\GenerationLock;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Port\Lock;

/**
 * The store is configurable (flock locally, Redis for a cluster — §7 of the DDD plan);
 * this class only depends on symfony/lock's LockFactory abstraction, never a specific
 * store, so the choice is entirely a DI-wiring concern (D4).
 */
final readonly class SymfonyGenerationLock implements GenerationLock
{
    public function __construct(
        private LockFactory $lockFactory,
        private float $ttlSeconds = 300.0,
    ) {
    }

    public function acquire(VariantId $id): ?Lock
    {
        $lock = $this->lockFactory->createLock('pgi-variant-'.$id->value, $this->ttlSeconds, false);

        if (!$lock->acquire(false)) {
            return null;
        }

        return new SymfonyLock($lock);
    }

    public function release(Lock $lock): void
    {
        if ($lock instanceof SymfonyLock) {
            $lock->inner->release();
        }
    }
}
