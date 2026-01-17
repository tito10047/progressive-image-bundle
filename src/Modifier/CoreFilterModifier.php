<?php

/*
 * This file is part of the Progressive Image Bundle.
 *
 * (c) Jozef Môstka <https://github.com/tito10047/progressive-image-bundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tito10047\ProgressiveImageBundle\Modifier;

class CoreFilterModifier implements FilterModifierInterface {

	private const ALLOWED_FILTERS = ['circle', 'grayscale', 'sepia'];

	public function supports(string $filterName): bool {
		return in_array($filterName, self::ALLOWED_FILTERS, true);
	}

	public function modify(string $filterName, array $currentOptions): array {
		$currentOptions['filter'] = $filterName;

		return $currentOptions;
	}
}
