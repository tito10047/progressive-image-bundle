<?php

/*
 * This file is part of the Progressive Image Bundle.
 *
 * (c) Jozef Môstka <https://github.com/tito10047/progressive-image-bundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tito10047\ProgressiveImageBundle\DTO;

class ResponsiveAttributes implements ResponsiveAttributesInterface {

	/**
	 * @param ResponsiveSourceInterface[] $sources
	 * @param array<string, string>       $variables
	 */
	public function __construct(
		private readonly array                     $sources,
		private readonly ResponsiveSourceInterface $defaultSource,
		private readonly array                     $variables = [],
	) {
	}

	public function getSources(): array {
		return $this->sources;
	}

	public function getDefaultSource(): ResponsiveSourceInterface {
		return $this->defaultSource;
	}

	public function getVariables(): array {
		return $this->variables;
	}
}
