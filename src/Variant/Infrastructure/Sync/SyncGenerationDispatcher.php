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

namespace Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Sync;

use Psr\Log\LoggerInterface;
use Tito10047\ProgressiveImageBundle\Variant\Application\Command\GenerateVariant;
use Tito10047\ProgressiveImageBundle\Variant\Application\Handler\GenerateVariantHandler;
use Tito10047\ProgressiveImageBundle\Variant\Application\Port\GenerationDispatcher;

/**
 * generation.strategy: sync — runs the Application handler in the request itself. Same
 * handler as the async/terminate strategies; only the "when" differs.
 */
final readonly class SyncGenerationDispatcher implements GenerationDispatcher
{
    public function __construct(
        private GenerateVariantHandler $handler,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function dispatch(GenerateVariant $command): void
    {
        try {
            ($this->handler)($command);
        } catch (\Throwable $e) {
            // GenerateVariantHandler already recorded the fail marker and published
            // VariantGenerationFailed — dispatch() must not let the failure escape as an
            // exception, or "sync" would be the only strategy where a broken source
            // crashes the whole page render instead of falling back to the original image
            // (ResolveVariantUrlHandler's PendingFallbackStrategy), since async/terminate
            // never run generation inline and so can never throw from here. The failure
            // itself must still be visible somewhere, so it never disappears silently.
            if ($this->logger) {
                $this->logger->warning('Synchronous variant generation failed; falling back to the original image.', [
                    'source' => $command->source->value,
                    'exception' => $e,
                ]);
            } else {
                error_log(sprintf('Synchronous variant generation failed for source "%s": %s', $command->source->value, $e->getMessage()));
            }
        }
    }
}
