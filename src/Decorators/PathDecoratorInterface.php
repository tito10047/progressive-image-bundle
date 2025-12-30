<?php

namespace Tito10047\ProgressiveImageBundle\Decorators;

interface PathDecoratorInterface {

	public function decorate(string $path, array $context = []):string;

	/**
	 * @return null|array{
	 *     width: int,
	 *     height: int
	 * }
	 */
	public function getSize(string $path, array $context = []):?array;
}