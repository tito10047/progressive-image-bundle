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

namespace Tito10047\ProgressiveImageBundle\Tests\Variant\Application\Service;

use PHPUnit\Framework\TestCase;
use Tito10047\ProgressiveImageBundle\Variant\Application\Service\PendingGenerationTracker;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\VariantId;

final class PendingGenerationTrackerTest extends TestCase
{
    public function testHasNoPendingVariantsInitially(): void
    {
        $tracker = new PendingGenerationTracker();

        self::assertFalse($tracker->hasPending());
    }

    public function testHasPendingAfterMarkPending(): void
    {
        $tracker = new PendingGenerationTracker();

        $tracker->markPending(new VariantId('abc'));

        self::assertTrue($tracker->hasPending());
    }

    public function testMarkingTheSameIdTwiceStaysPending(): void
    {
        $tracker = new PendingGenerationTracker();

        $tracker->markPending(new VariantId('abc'));
        $tracker->markPending(new VariantId('abc'));

        self::assertTrue($tracker->hasPending());
    }

    public function testResetClearsPendingState(): void
    {
        $tracker = new PendingGenerationTracker();
        $tracker->markPending(new VariantId('abc'));

        $tracker->reset();

        self::assertFalse($tracker->hasPending());
    }
}
