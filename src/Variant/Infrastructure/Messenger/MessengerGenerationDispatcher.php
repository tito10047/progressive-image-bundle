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

namespace Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Messenger;

use Symfony\Component\Messenger\MessageBusInterface;
use Tito10047\ProgressiveImageBundle\Variant\Application\Command\GenerateVariant;
use Tito10047\ProgressiveImageBundle\Variant\Application\Port\GenerationDispatcher;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Service\VariantIdHasher;

/**
 * generation.strategy: async (the default). Per-request dedup only — the GenerationLock in
 * GenerateVariantHandler plus the storage-exists idempotency check are what actually protect
 * against duplicate work across requests/servers; a worst case here is a duplicate no-op job
 * (§7 of the DDD plan), not a correctness problem.
 */
final class MessengerGenerationDispatcher implements GenerationDispatcher
{
    /** @var array<string, true> */
    private array $dispatchedThisRequest = [];

    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly VariantIdHasher $hasher,
    ) {
    }

    public function dispatch(GenerateVariant $command): void
    {
        $id = $this->hasher->hash($command->source, $command->spec)->value;

        if (isset($this->dispatchedThisRequest[$id])) {
            return;
        }

        $this->dispatchedThisRequest[$id] = true;
        $this->bus->dispatch($command);
    }
}
