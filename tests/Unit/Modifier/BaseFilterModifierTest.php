<?php

/*
 * This file is part of the Progressive Image Bundle.
 *
 * (c) Jozef Môstka <https://github.com/tito10047/progressive-image-bundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tito10047\ProgressiveImageBundle\Tests\Unit\Modifier;

use PHPUnit\Framework\TestCase;
use Tito10047\ProgressiveImageBundle\Modifier\BaseFilterModifier;

class BaseFilterModifierTest extends TestCase
{
    public function testSupportsAlwaysReturnsTrue(): void
    {
        $modifier = new BaseFilterModifier();
        $this->assertTrue($modifier->supports('any'));
        $this->assertTrue($modifier->supports('circle'));
    }

    public function testModifyAddsFilterToContext(): void
    {
        $modifier = new BaseFilterModifier();
        $context = ['foo' => 'bar'];
        $result = $modifier->modify('circle', $context);

        $this->assertEquals([
            'foo' => 'bar',
            'filter' => 'circle',
        ], $result);
    }

    public function testModifyDoesNotOverrideExistingFilter(): void
    {
        $modifier = new BaseFilterModifier();
        $context = ['filter' => 'original'];
        $result = $modifier->modify('new_filter', $context);

        $this->assertEquals([
            'filter' => 'original',
        ], $result);
    }
}
