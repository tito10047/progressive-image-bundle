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

use Tito10047\ProgressiveImageBundle\Variant\Application\Command\GenerateVariant;
use Tito10047\ProgressiveImageBundle\Variant\Application\Handler\GenerateVariantHandler;
use Tito10047\ProgressiveImageBundle\Variant\Application\Port\GenerationDispatcher;

/**
 * generation.strategy: sync — runs the Application handler in the request itself. Same
 * handler as the async/terminate strategies; only the "when" differs.
 */
final readonly class SyncGenerationDispatcher implements GenerationDispatcher
{
    public function __construct(private GenerateVariantHandler $handler)
    {
    }

    public function dispatch(GenerateVariant $command): void
    {
        ($this->handler)($command);
    }
}
