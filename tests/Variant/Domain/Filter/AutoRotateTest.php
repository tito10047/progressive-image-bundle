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
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\AutoRotate;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\Filter;

final class AutoRotateTest extends TestCase
{
    public function testImplementsFilter(): void
    {
        self::assertInstanceOf(Filter::class, new AutoRotate());
    }

    public function testCanonical(): void
    {
        self::assertSame(['auto_rotate' => true], (new AutoRotate())->canonical());
    }
}
