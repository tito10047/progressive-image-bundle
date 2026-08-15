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

namespace Tito10047\ProgressiveImageBundle\Tests\Variant\Domain\Filter;

use PHPUnit\Framework\TestCase;
use Tito10047\ProgressiveImageBundle\Tests\Variant\Double\AnotherFakeFilter;
use Tito10047\ProgressiveImageBundle\Tests\Variant\Double\FakeFilter;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\FilterChain;

final class FilterChainTest extends TestCase
{
    public function testEmptyChainHasNoFiltersAndEmptyCanonical(): void
    {
        $chain = FilterChain::empty();

        self::assertCount(0, $chain);
        self::assertTrue($chain->isEmpty());
        self::assertSame([], $chain->canonical());
    }

    public function testOfBuildsChainInGivenOrder(): void
    {
        $first = new FakeFilter('first');
        $second = new FakeFilter('second');

        $chain = FilterChain::of($first, $second);

        self::assertCount(2, $chain);
        self::assertFalse($chain->isEmpty());
        self::assertSame([$first, $second], iterator_to_array($chain, false));
    }

    public function testCanonicalPreservesOrder(): void
    {
        $chain = FilterChain::of(new FakeFilter('a'), new FakeFilter('b'));

        self::assertSame(
            [['fake' => 'a'], ['fake' => 'b']],
            $chain->canonical()
        );
    }

    public function testWithAppendsFilterAndReturnsNewInstance(): void
    {
        $original = FilterChain::of(new FakeFilter('a'));
        $appended = $original->with(new FakeFilter('b'));

        self::assertCount(1, $original, 'original chain must stay untouched (immutability)');
        self::assertCount(2, $appended);
        self::assertSame(
            [['fake' => 'a'], ['fake' => 'b']],
            $appended->canonical()
        );
    }

    public function testWithoutRemovesFiltersByClassAndReturnsNewInstance(): void
    {
        $crop = new FakeFilter('crop');
        $thumbnail = new AnotherFakeFilter();
        $original = FilterChain::of($crop, $thumbnail);

        $withoutFakes = $original->without(FakeFilter::class);

        self::assertCount(2, $original, 'original chain must stay untouched (immutability)');
        self::assertCount(1, $withoutFakes);
        self::assertSame([$thumbnail], iterator_to_array($withoutFakes, false));
    }

    public function testWithoutAcceptsMultipleClassesAndReindexesList(): void
    {
        $chain = FilterChain::of(new FakeFilter('a'), new AnotherFakeFilter(), new FakeFilter('b'));

        $result = $chain->without(FakeFilter::class, AnotherFakeFilter::class);

        self::assertCount(0, $result);
        self::assertSame([], $result->canonical());
    }
}
