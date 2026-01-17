<?php

/*
 * This file is part of the Progressive Image Bundle.
 *
 * (c) Jozef Môstka <https://github.com/tito10047/progressive-image-bundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tito10047\ProgressiveImageBundle\UrlGenerator;

class DefaultResponsiveImageUrlGenerator implements ResponsiveImageUrlGeneratorInterface {

	public function generateUrl(string $path, int $targetW, ?int $targetH, ?string $pointInterest = null, array $context = []): string {
		// Simple fallback that just returns the path.
		// In a real scenario, this might be replaced by something more sophisticated
		// if LiipImagine is not used.
		return $path;
	}
}
