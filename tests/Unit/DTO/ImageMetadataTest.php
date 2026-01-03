<?php

/*
 * This file is part of the Progressive Image Bundle.
 *
 * (c) Jozef Môstka <https://github.com/tito10047/progressive-image-bundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tito10047\ProgressiveImageBundle\Tests\Unit\DTO;

use PHPUnit\Framework\TestCase;
use Tito10047\ProgressiveImageBundle\DTO\ImageMetadata;

class ImageMetadataTest extends TestCase
{
    public function testConstructorAndProperties(): void
    {
        $hash = 'L6PZfS_NcD.9_3%2IAaxu_N%4MWB';
        $width = 800;
        $height = 600;

        $metadata = new ImageMetadata($hash, $width, $height);

        $this->assertSame($hash, $metadata->originalHash);
        $this->assertSame($width, $metadata->width);
        $this->assertSame($height, $metadata->height);
    }
}
