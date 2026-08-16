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
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\Grayscale;

final class GrayscaleTest extends TestCase
{
    public function testImplementsFilter(): void
    {
        self::assertInstanceOf(Filter::class, new Grayscale());
    }

    public function testCanonical(): void
    {
        self::assertSame(['grayscale' => true], (new Grayscale())->canonical());
    }
}
