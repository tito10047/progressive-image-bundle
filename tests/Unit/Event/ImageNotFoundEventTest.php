<?php

namespace Tito10047\ProgressiveImageBundle\Tests\Unit\Event;

use PHPUnit\Framework\TestCase;
use Tito10047\ProgressiveImageBundle\Event\ImageNotFoundEvent;

class ImageNotFoundEventTest extends TestCase
{
    public function testEventProperties(): void
    {
        $path = 'test.jpg';
        $loaderClass = 'SomeLoader';
        $event = new ImageNotFoundEvent($path, $loaderClass);

        $this->assertSame($path, $event->getPath());
        $this->assertSame($loaderClass, $event->getLoaderClass());
        $this->assertInstanceOf(\DateTimeImmutable::class, $event->getOccurredAt());
    }
}
