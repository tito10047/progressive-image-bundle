<?php

/*
 * This file is part of the Progressive Image Bundle.
 *
 * (c) Jozef Môstka <https://github.com/tito10047/progressive-image-bundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tito10047\ProgressiveImageBundle\Tests\Unit\Resolver;

use PHPUnit\Framework\TestCase;
use Tito10047\ProgressiveImageBundle\Exception\PathResolutionException;
use Tito10047\ProgressiveImageBundle\Resolver\ChainResolver;
use Tito10047\ProgressiveImageBundle\Resolver\PathResolverInterface;

final class ChainResolverTest extends TestCase
{
    public function testResolversIterableIsOnlyConsumedOnceEvenAcrossMultipleResolveCalls(): void
    {
        // First resolver always misses, so the loop must call next() to reach the second
        // one — this is what actually advances (and, pre-fix, exhausts) a one-shot generator.
        $missing = new class implements PathResolverInterface {
            public function resolve(string $path): string
            {
                throw new PathResolutionException('never resolves');
            }
        };
        $fallback = new class implements PathResolverInterface {
            public function resolve(string $path): string
            {
                return '/resolved/'.$path;
            }
        };

        // A one-shot generator, as an application-provided iterable (e.g. via
        // TaggedIteratorArgument) might be.
        $resolvers = (function () use ($missing, $fallback) {
            yield $missing;
            yield $fallback;
        })();

        $chain = new ChainResolver($resolvers);

        self::assertSame('/resolved/a.jpg', $chain->resolve('a.jpg'));
        self::assertSame('/resolved/b.jpg', $chain->resolve('b.jpg'));
    }

    public function testThrowsWhenNoResolverCanResolveThePath(): void
    {
        $resolvers = (function () {
            return;
            yield;
        })();

        $chain = new ChainResolver($resolvers);

        $this->expectException(PathResolutionException::class);
        $chain->resolve('a.jpg');
    }
}
