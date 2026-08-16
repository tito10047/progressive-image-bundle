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

namespace Tito10047\ProgressiveImageBundle\Variant\Application\Query;

/**
 * generation.fallback_while_pending from the bundle config (§9 of the DDD plan):
 * what a caller gets back while a variant is still generating.
 */
enum PendingFallbackStrategy
{
    case Original;
    case Wait;
}
