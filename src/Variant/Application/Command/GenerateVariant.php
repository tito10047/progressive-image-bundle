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

namespace Tito10047\ProgressiveImageBundle\Variant\Application\Command;

use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\SourcePath;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\VariantSpec;

/**
 * Carries only plain, immutable Domain value objects — no service references — so it is
 * safe to serialize onto any Messenger transport. The VariantId is deliberately not
 * included: the handler recomputes it from (source, spec) via VariantIdHasher, which is
 * content-addressed and therefore idempotent by construction.
 */
final readonly class GenerateVariant
{
    public function __construct(public SourcePath $source, public VariantSpec $spec)
    {
    }
}
