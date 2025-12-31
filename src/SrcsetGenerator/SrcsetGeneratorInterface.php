<?php

namespace Tito10047\ProgressiveImageBundle\SrcsetGenerator;

interface SrcsetGeneratorInterface {

	/**
	 * @param array<string, int> $breakpoints
	 * @return array<string, string>
	 */
	public function generate(string $path, array $breakpoints, array $context = []): array;
}