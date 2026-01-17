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

class BaseFilterModifier implements ModifierInterface {

	public function __construct(
		private readonly FilterManager $filterManager,
	) {
	}

	public function supports(string $modifier): bool {
		// Podporuje všetko, čo sa nepodarilo spracovať predchádzajúcim Modifierom
		// Musíme ale vrátiť true len ak FilterManager niečo spracuje,
		// alebo ak chceme aby to bol fallback ktorý vráti custom_filters.
		return true;
	}

	public function modify(string $modifier, array $context): array {
		return $this->filterManager->applyFilters($modifier, $context);
	}
}
