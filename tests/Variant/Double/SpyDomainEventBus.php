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

use Tito10047\ProgressiveImageBundle\Variant\Application\Port\DomainEventBus;

final class SpyDomainEventBus implements DomainEventBus
{
    /** @var list<object> */
    private array $published = [];

    public function publish(object $event): void
    {
        $this->published[] = $event;
    }

    /** @return list<object> */
    public function published(): array
    {
        return $this->published;
    }
}
