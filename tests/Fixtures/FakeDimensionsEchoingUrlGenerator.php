<?php

/*
 * This file is part of the Progressive Image Bundle.
 *
 * (c) Jozef Môstka <https://github.com/tito10047/progressive-image-bundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tito10047\ProgressiveImageBundle\Tests\Fixtures;

use Tito10047\ProgressiveImageBundle\UrlGenerator\ResponsiveImageUrlGeneratorInterface;

/**
 * Echoes the computed target width/height as query params — the old Liip-based generator
 * embedded them the same way in its unsigned fallback URL. Tests that check ratio/grid math
 * by inspecting the srcset need *some* generator that reflects computed dimensions into the
 * URL; DefaultResponsiveImageUrlGenerator deliberately doesn't (it just returns the path).
 */
final class FakeDimensionsEchoingUrlGenerator implements ResponsiveImageUrlGeneratorInterface
{
    public function generateUrl(string $path, int $targetW, ?int $targetH = null, ?string $pointInterest = null, array $context = []): string
    {
        return $path.'?width='.$targetW.'&height='.($targetH ?? $targetW);
    }
}
