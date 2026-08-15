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

namespace Tito10047\ProgressiveImageBundle\Variant\Application\Port;

use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\SourcePath;

/**
 * Not in the original plan's port list, but required by ResolveVariantUrlHandler's
 * fallback_while_pending=original branch (§6.1): it needs the Source's own public URL.
 * Infrastructure will adapt this onto the existing Rendering-context resolvers
 * (src/Resolver/ChainResolver et al.) — no new resolution logic, just a seam.
 */
interface OriginalUrlResolver
{
    public function resolve(SourcePath $source): string;
}
