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

namespace Tito10047\ProgressiveImageBundle\Variant\Domain\Filter;

/**
 * Marker interface for a single, immutable filter operation within a FilterChain.
 */
interface Filter
{
    /**
     * Canonical representation used as the sole input to VariantId hashing.
     * Key order and value types must be stable — changing this is a hash-schema break.
     *
     * @return array<string, mixed>
     */
    public function canonical(): array;
}
