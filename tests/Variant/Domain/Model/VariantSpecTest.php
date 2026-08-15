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
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\FilterChain;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\Thumbnail;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\Dimensions;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\OutputFormat;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\Quality;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\VariantSpec;

final class VariantSpecTest extends TestCase
{
    public function testExposesConstructorArguments(): void
    {
        $filters = FilterChain::of(Thumbnail::outbound(new Dimensions(200, 200)));
        $spec = new VariantSpec($filters, OutputFormat::Webp, new Quality(82));

        self::assertSame($filters, $spec->filters);
        self::assertSame(OutputFormat::Webp, $spec->format);
        self::assertSame(82, $spec->quality->value);
    }

    public function testCanonicalCombinesFiltersFormatAndQuality(): void
    {
        $filters = FilterChain::of(Thumbnail::outbound(new Dimensions(200, 200)));
        $spec = new VariantSpec($filters, OutputFormat::Webp, new Quality(82));

        self::assertSame(
            [
                'filters' => [['thumbnail' => ['w' => 200, 'h' => 200, 'mode' => 'outbound']]],
                'format' => 'webp',
                'quality' => 82,
            ],
            $spec->canonical()
        );
    }

    public function testCanonicalOfEmptyFilterChain(): void
    {
        $spec = new VariantSpec(FilterChain::empty(), OutputFormat::Jpeg, new Quality(85));

        self::assertSame(
            ['filters' => [], 'format' => 'jpeg', 'quality' => 85],
            $spec->canonical()
        );
    }
}
