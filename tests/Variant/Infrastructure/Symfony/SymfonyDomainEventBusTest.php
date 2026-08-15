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

namespace Tito10047\ProgressiveImageBundle\Tests\Variant\Infrastructure\Symfony;

use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Event\VariantGenerated;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\VariantId;
use Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Symfony\SymfonyDomainEventBus;

final class SymfonyDomainEventBusTest extends TestCase
{
    public function testPublishDispatchesTheEventUnderItsOwnClassName(): void
    {
        $dispatcher = new EventDispatcher();
        $received = null;
        $dispatcher->addListener(VariantGenerated::class, function (VariantGenerated $event) use (&$received): void {
            $received = $event;
        });

        $bus = new SymfonyDomainEventBus($dispatcher);
        $event = new VariantGenerated(new VariantId('abc'));
        $bus->publish($event);

        self::assertSame($event, $received);
    }
}
