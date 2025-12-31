<?php

namespace Tito10047\ProgressiveImageBundle\Tests\Unit\SrcsetGenerator;

use PHPUnit\Framework\TestCase;
use Tito10047\ProgressiveImageBundle\Decorators\LiipImagineDecorator;
use Tito10047\ProgressiveImageBundle\SrcsetGenerator\LiipImagineSrcsetGenerator;

class LiipImagineSrcsetGeneratorTest extends TestCase
{
    public function testGenerate(): void
    {
        $decorator = $this->createMock(LiipImagineDecorator::class);
        $decorator->expects($this->exactly(2))
            ->method('decorate')
            ->willReturnMap([
                ['image.jpg', ['filter' => 'progressive_image_filter_sm'], '/cache/sm/image.jpg'],
                ['image.jpg', ['filter' => 'progressive_image_filter_md'], '/cache/md/image.jpg'],
            ]);

        $generator = new LiipImagineSrcsetGenerator($decorator);
        $breakpoints = ['sm' => 480, 'md' => 800];
        
        $result = $generator->generate('image.jpg', $breakpoints);

        $this->assertEquals([
            'sm' => '/cache/sm/image.jpg',
            'md' => '/cache/md/image.jpg',
        ], $result);
    }
}
