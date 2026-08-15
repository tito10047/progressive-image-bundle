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

namespace Tito10047\ProgressiveImageBundle\Tests\Variant\Domain\Model;

use PHPUnit\Framework\TestCase;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Exception\InvalidFilterDefinition;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\VariantId;

final class VariantIdTest extends TestCase
{
    public function testWrapsValue(): void
    {
        $id = new VariantId('abc123');

        self::assertSame('abc123', $id->value);
        self::assertSame('abc123', (string) $id);
    }

    public function testRejectsEmptyValue(): void
    {
        $this->expectException(InvalidFilterDefinition::class);

        new VariantId('');
    }

    public function testEqualsComparesByValue(): void
    {
        $a = new VariantId('same');
        $b = new VariantId('same');
        $c = new VariantId('different');

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }
}
