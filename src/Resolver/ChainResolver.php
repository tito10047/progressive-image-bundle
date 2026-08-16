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

class ChainResolver implements PathResolverInterface
{
    /**
     * @var array<PathResolverInterface>
     */
    private readonly array $resolvers;

    /**
     * @param iterable<PathResolverInterface> $resolvers
     */
    public function __construct(
        iterable $resolvers,
    ) {
        // Materialized once: a TaggedIteratorArgument-injected iterable may be a one-shot
        // \Generator, and resolve() is typically called once per image within a request.
        $this->resolvers = is_array($resolvers) ? $resolvers : iterator_to_array($resolvers, false);
    }

    public function resolve(string $path): string
    {
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
