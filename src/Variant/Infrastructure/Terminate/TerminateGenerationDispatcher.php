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

use Psr\Log\LoggerInterface;
use Tito10047\ProgressiveImageBundle\Variant\Application\Command\GenerateVariant;
use Tito10047\ProgressiveImageBundle\Variant\Application\Handler\GenerateVariantHandler;
use Tito10047\ProgressiveImageBundle\Variant\Application\Port\GenerationDispatcher;

/**
 * generation.strategy: terminate — the fallback when Messenger isn't installed. Queues
 * commands during the request; DI wiring (D4) registers onTerminate() as a kernel.terminate
 * listener.
 *
 * Unlike ResolveVariantUrlHandler, this class must be registered SHARED (the DI default),
 * not setShared(false): it's resolved through two different paths on the same request — the
 * GenerationDispatcher alias (constructor-injected wherever dispatch() is called) and the
 * kernel.event_listener tag (which resolves it again to call onTerminate()). A non-shared
 * registration makes those two resolutions return different instances, so onTerminate()
 * flushes an empty queue instead of the one dispatch() actually populated — this was tried
 * and reproduces as a real, verified failure, not just a theoretical concern.
 *
 * In a classic PHP-FPM deployment the container (and this instance) doesn't outlive one
 * request, so this is moot. On a persistent worker (Swoole/RoadRunner/FrankenPHP), the same
 * shared instance does survive across requests, but onTerminate() unconditionally empties
 * $queued as its very first action on every kernel.terminate — so state never leaks *past*
 * a request's own termination. The one residual, accepted quirk on such runtimes: if two
 * requests interleave before either terminates, a command queued by request B could get
 * flushed during request A's termination instead of its own — GenerateVariantHandler is
 * idempotent (content-addressed VariantId, storage-exists check), so this only means the
 * generation runs slightly earlier than expected, never a lost or duplicated variant.
 */
final class TerminateGenerationDispatcher implements GenerationDispatcher
{
    /** @var list<GenerateVariant> */
    private array $queued = [];

    public function __construct(
        private readonly GenerateVariantHandler $handler,
        private readonly ?LoggerInterface $logger = null,
    ) {
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
            try {
                ($this->handler)($command);
            } catch (\Throwable $e) {
                // GenerateVariantHandler already recorded the fail marker and published
                // VariantGenerationFailed for this one command — one failure must not abort
                // the rest of the queue (other images from the same page still waiting on
                // this same kernel.terminate flush).
                if ($this->logger) {
                    $this->logger->warning('Deferred variant generation failed on kernel.terminate.', [
                        'source' => $command->source->value,
                        'exception' => $e,
                    ]);
                } else {
                    error_log(sprintf('Deferred variant generation failed on kernel.terminate for source "%s": %s', $command->source->value, $e->getMessage()));
                }
            }
        }
    }
}
