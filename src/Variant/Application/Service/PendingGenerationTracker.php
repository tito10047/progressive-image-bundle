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

namespace Tito10047\ProgressiveImageBundle\Variant\Application\Service;

use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\VariantId;

/**
 * The one shared-kernel touchpoint between the Variant context and the HTTP Cache Guard
 * context (ResponseCacheOverrideListener, TransparentImageCacheSubscriber): a per-request
 * record of which variants were dispatched for generation this request, so the response
 * can be forced to no-store instead of caching a page that references not-yet-ready
 * images. Infrastructure wires this as a non-shared/request-scoped service and calls
 * reset() on kernel.request; this class itself has no request-lifecycle awareness.
 */
final class PendingGenerationTracker
{
    /** @var array<string, true> */
    private array $pending = [];

    public function markPending(VariantId $id): void
    {
        $this->pending[$id->value] = true;
    }

    public function hasPending(): bool
    {
        return [] !== $this->pending;
    }

    public function reset(): void
    {
        $this->pending = [];
    }
}
