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

namespace Tito10047\ProgressiveImageBundle\Tests\Unit\Twig;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Tito10047\ProgressiveImageBundle\Twig\TransparentCacheExtension;
use Tito10047\ProgressiveImageBundle\Variant\Application\Service\PendingGenerationTracker;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\VariantId;

final class TransparentCacheExtensionTest extends TestCase
{
    public function testWritesToCacheWhenNothingIsPending(): void
    {
        $cache = new TagAwareAdapter(new ArrayAdapter());
        $extension = new TransparentCacheExtension($cache, null, new PendingGenerationTracker());

        $extension->saveToCache('<img>', 'my-key');

        self::assertTrue($cache->getItem('my-key')->isHit());
    }

    public function testDoesNotWriteToCacheWhilePendingVariantsExist(): void
    {
        $cache = new TagAwareAdapter(new ArrayAdapter());
        $tracker = new PendingGenerationTracker();
        $tracker->markPending(new VariantId('abc'));
        $extension = new TransparentCacheExtension($cache, null, $tracker);

        $extension->saveToCache('<img>', 'my-key');

        self::assertFalse($cache->getItem('my-key')->isHit());
    }

    public function testStillReturnsTheContentWhenSkippingTheCacheWrite(): void
    {
        $cache = new TagAwareAdapter(new ArrayAdapter());
        $tracker = new PendingGenerationTracker();
        $tracker->markPending(new VariantId('abc'));
        $extension = new TransparentCacheExtension($cache, null, $tracker);

        self::assertSame('<img>', $extension->saveToCache('<img>', 'my-key'));
    }

    public function testWorksWithoutATrackerForBackwardCompatibility(): void
    {
        $cache = new TagAwareAdapter(new ArrayAdapter());
        $extension = new TransparentCacheExtension($cache, null);

        $extension->saveToCache('<img>', 'my-key');

        self::assertTrue($cache->getItem('my-key')->isHit());
    }
}
