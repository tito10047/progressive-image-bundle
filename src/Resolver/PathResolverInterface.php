<?php

namespace Tito10047\ProgressiveImageBundle\Resolver;

use Tito10047\ProgressiveImageBundle\Exception\PathResolutionException;

interface PathResolverInterface {
	/**
	 * @throws PathResolutionException
	 */
	public function resolve(string $path): string;
}