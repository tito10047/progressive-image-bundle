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
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\Paste;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\SourcePath;

final class PasteTest extends TestCase
{
    public function testImplementsFilter(): void
    {
        self::assertInstanceOf(Filter::class, new Paste(new SourcePath('badge.png')));
    }

    public function testCanonicalWithDefaultPosition(): void
    {
        $paste = new Paste(new SourcePath('badge.png'));

        self::assertSame(['paste' => ['image' => 'badge.png', 'x' => 0, 'y' => 0]], $paste->canonical());
    }

    public function testCanonicalWithExplicitPosition(): void
    {
        $paste = new Paste(new SourcePath('badge.png'), 20, 30);

        self::assertSame(['paste' => ['image' => 'badge.png', 'x' => 20, 'y' => 30]], $paste->canonical());
    }
}
