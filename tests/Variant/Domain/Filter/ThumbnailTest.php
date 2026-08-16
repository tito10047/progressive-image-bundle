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
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\Filter;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\Thumbnail;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\Dimensions;

final class ThumbnailTest extends TestCase
{
    public function testInsetBuildsThumbnailWithInsetMode(): void
    {
        $thumbnail = Thumbnail::inset(new Dimensions(200, 100));

        self::assertInstanceOf(Filter::class, $thumbnail);
        self::assertSame(200, $thumbnail->size->width);
        self::assertSame(100, $thumbnail->size->height);
        self::assertSame(
            ['thumbnail' => ['w' => 200, 'h' => 100, 'mode' => 'inset']],
            $thumbnail->canonical()
        );
    }

    public function testOutboundBuildsThumbnailWithOutboundMode(): void
    {
        $thumbnail = Thumbnail::outbound(new Dimensions(50, 50));

        self::assertSame(
            ['thumbnail' => ['w' => 50, 'h' => 50, 'mode' => 'outbound']],
            $thumbnail->canonical()
        );
    }
}
