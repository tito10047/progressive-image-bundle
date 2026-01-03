<?php

/*
 * This file is part of the Progressive Image Bundle.
 *
 * (c) Jozef Môstka <https://github.com/tito10047/progressive-image-bundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
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