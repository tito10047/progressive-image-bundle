<?php

/*
 * This file is part of the Progressive Image Bundle.
 *
 * (c) Jozef Môstka <https://github.com/tito10047/progressive-image-bundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tito10047\ProgressiveImageBundle\Resolver;

use Tito10047\ProgressiveImageBundle\Exception\PathResolutionException;

class ChainResolver implements PathResolverInterface {

	/**
	 * @param iterable<PathResolverInterface> $resolvers
	 */
	public function __construct(
		private readonly iterable $resolvers,
	) {
	}

	public function resolve(string $path): string {
		foreach ($this->resolvers as $resolver) {
			try {
				return $resolver->resolve($path);
			} catch (PathResolutionException) {
				continue;
			}
		}

		throw new PathResolutionException(sprintf('Path "%s" could not be resolved by any of the registered resolvers.', $path));
	}
}
