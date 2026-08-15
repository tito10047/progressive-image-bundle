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

use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Tito10047\ProgressiveImageBundle\Variant\Application\Command\GenerateVariant;
use Tito10047\ProgressiveImageBundle\Variant\Application\Handler\GenerateVariantHandler;

/**
 * Deliberately thin — the generation logic lives once, in the Application handler. The
 * sync and terminate dispatch strategies call that same handler directly.
 */
#[AsMessageHandler]
final readonly class GenerateVariantMessageHandler
{
    public function __construct(private GenerateVariantHandler $handler)
    {
    }

    public function __invoke(GenerateVariant $command): void
    {
        ($this->handler)($command);
    }
}
