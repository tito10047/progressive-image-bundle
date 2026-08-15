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

namespace Tito10047\ProgressiveImageBundle\Tests\Variant\Application\Command;

use PHPUnit\Framework\TestCase;
use Tito10047\ProgressiveImageBundle\Variant\Application\Command\GenerateVariant;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\FilterChain;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\Thumbnail;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\Dimensions;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\OutputFormat;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\Quality;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\SourcePath;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\VariantSpec;

final class GenerateVariantTest extends TestCase
{
    public function testCarriesSourceAndSpecOnly(): void
    {
        $source = new SourcePath('uploads/hero.jpg');
        $spec = new VariantSpec(FilterChain::of(Thumbnail::outbound(new Dimensions(200, 200))), OutputFormat::Webp, new Quality(82));

        $command = new GenerateVariant($source, $spec);

        self::assertSame($source, $command->source);
        self::assertSame($spec, $command->spec);
    }

    public function testIsSerializableForAnyTransport(): void
    {
        $source = new SourcePath('uploads/hero.jpg');
        $spec = new VariantSpec(FilterChain::of(Thumbnail::outbound(new Dimensions(200, 200))), OutputFormat::Webp, new Quality(82));
        $command = new GenerateVariant($source, $spec);

        $restored = unserialize(serialize($command));

        self::assertInstanceOf(GenerateVariant::class, $restored);
        self::assertSame($source->value, $restored->source->value);
        self::assertSame($spec->canonical(), $restored->spec->canonical());
    }
}
