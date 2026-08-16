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

namespace Tito10047\ProgressiveImageBundle\Tests\Variant\Infrastructure\Symfony;

use PHPUnit\Framework\TestCase;
use Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Symfony\SystemClock;

final class SystemClockTest extends TestCase
{
    public function testNowReturnsTheCurrentWallClockTime(): void
    {
        $clock = new SystemClock();

        $before = new \DateTimeImmutable();
        $now = $clock->now();
        $after = new \DateTimeImmutable();

        self::assertGreaterThanOrEqual($before->getTimestamp(), $now->getTimestamp());
        self::assertLessThanOrEqual($after->getTimestamp(), $now->getTimestamp());
    }

    public function testEachCallReflectsTheCurrentMoment(): void
    {
        $clock = new SystemClock();

        $first = $clock->now();
        usleep(1000);
        $second = $clock->now();

        self::assertGreaterThanOrEqual($first, $second);
    }
}
