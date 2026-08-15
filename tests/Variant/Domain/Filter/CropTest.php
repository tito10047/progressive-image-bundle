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
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\Crop;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\Filter;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\CropBox;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\Dimensions;

final class CropTest extends TestCase
{
    public function testWrapsCropBoxAndExposesIt(): void
    {
        $box = new CropBox(10, 20, new Dimensions(100, 50));
        $crop = new Crop($box);

        self::assertInstanceOf(Filter::class, $crop);
        self::assertSame($box, $crop->box);
    }

    public function testCanonicalRepresentation(): void
    {
        $crop = new Crop(new CropBox(10, 20, new Dimensions(100, 50)));

        self::assertSame(
            ['crop' => ['x' => 10, 'y' => 20, 'w' => 100, 'h' => 50]],
            $crop->canonical()
        );
    }
}
