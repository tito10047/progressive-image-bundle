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
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\Resize;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\Dimensions;

final class ResizeTest extends TestCase
{
    public function testWrapsTargetDimensions(): void
    {
        $resize = new Resize(new Dimensions(640, 480));

        self::assertInstanceOf(Filter::class, $resize);
        self::assertSame(640, $resize->size->width);
        self::assertSame(480, $resize->size->height);
    }

    public function testCanonicalRepresentation(): void
    {
        $resize = new Resize(new Dimensions(640, 480));

        self::assertSame(['resize' => ['w' => 640, 'h' => 480]], $resize->canonical());
    }
}
