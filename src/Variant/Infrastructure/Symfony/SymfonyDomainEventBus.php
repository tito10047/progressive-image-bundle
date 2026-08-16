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

namespace Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Symfony;

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Tito10047\ProgressiveImageBundle\Variant\Application\Port\DomainEventBus;

/**
 * The Domain events (VariantRequested, VariantGenerated, VariantGenerationFailed) are
 * public API for bundle users — logging, metrics, whatever they need — so this dispatches
 * them under their own class name, the normal Symfony EventDispatcher convention.
 */
final readonly class SymfonyDomainEventBus implements DomainEventBus
{
    public function __construct(private EventDispatcherInterface $dispatcher)
    {
    }

    public function publish(object $event): void
    {
        $this->dispatcher->dispatch($event);
    }
}
