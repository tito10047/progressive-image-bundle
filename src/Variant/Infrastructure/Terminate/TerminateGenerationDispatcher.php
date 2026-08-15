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

namespace Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Terminate;

use Tito10047\ProgressiveImageBundle\Variant\Application\Command\GenerateVariant;
use Tito10047\ProgressiveImageBundle\Variant\Application\Handler\GenerateVariantHandler;
use Tito10047\ProgressiveImageBundle\Variant\Application\Port\GenerationDispatcher;

/**
 * generation.strategy: terminate — the fallback when Messenger isn't installed. Queues
 * commands during the request; DI wiring (D4) registers onTerminate() as a kernel.terminate
 * listener, so this class must be request-scoped (non-shared), same requirement as
 * ResolveVariantUrlHandler.
 */
final class TerminateGenerationDispatcher implements GenerationDispatcher
{
    /** @var list<GenerateVariant> */
    private array $queued = [];

    public function __construct(private readonly GenerateVariantHandler $handler)
    {
    }

    public function dispatch(GenerateVariant $command): void
    {
        $this->queued[] = $command;
    }

    public function onTerminate(): void
    {
        $queued = $this->queued;
        $this->queued = [];

        foreach ($queued as $command) {
            ($this->handler)($command);
        }
    }
}
