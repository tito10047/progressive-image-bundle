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

use Tito10047\ProgressiveImageBundle\Variant\Application\Command\GenerateVariant;
use Tito10047\ProgressiveImageBundle\Variant\Application\Port\GenerationDispatcher;

final class SpyGenerationDispatcher implements GenerationDispatcher
{
    /** @var list<GenerateVariant> */
    private array $dispatched = [];

    public function dispatch(GenerateVariant $command): void
    {
        $this->dispatched[] = $command;
    }

    /** @return list<GenerateVariant> */
    public function dispatched(): array
    {
        return $this->dispatched;
    }
}
