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

class FilterManager {

	/**
	 * @param iterable<FilterModifierInterface> $modifiers
	 */
	public function __construct(
		private readonly iterable $modifiers,
	) {
	}

	/**
	 * @param array<string, mixed> $options
	 *
	 * @return array<string, mixed>
	 */
	public function applyFilters(string $filtersString, array $options = []): array {
		$filterNames = explode('|', $filtersString);

		foreach ($filterNames as $name) {
			$supported = false;
			foreach ($this->modifiers as $modifier) {
				if ($modifier->supports($name)) {
					$options   = $modifier->modify($name, $options);
					$supported = true;
					break;
				}
			}

			if (!$supported) {
				$options['custom_filters'][] = $name;
			}
		}

		return $options;
	}
}
