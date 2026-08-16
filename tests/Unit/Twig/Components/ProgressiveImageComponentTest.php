<?php

/*
 * This file is part of the Progressive Image Bundle.
 *
 * (c) Jozef Môstka <https://github.com/tito10047/progressive-image-bundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tito10047\ProgressiveImageBundle\Tests\Unit\Twig\Components;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Tito10047\ProgressiveImageBundle\Decorators\PathDecoratorInterface;
use Tito10047\ProgressiveImageBundle\DTO\ImageMetadata;
use Tito10047\ProgressiveImageBundle\Exception\PathResolutionException;
use Tito10047\ProgressiveImageBundle\Service\MetadataReaderInterface;
use Tito10047\ProgressiveImageBundle\Service\PreloadCollector;
use Tito10047\ProgressiveImageBundle\Service\ResponsiveAttributeGenerator;
use Tito10047\ProgressiveImageBundle\Twig\Components\Image;
use Tito10047\ProgressiveImageBundle\UrlGenerator\ResponsiveImageUrlGeneratorInterface;

// $collector is only ever injected here, never verified — a legitimate stub-style use of
// createMock() alongside other mocks in the same test that do configure ->expects().
#[AllowMockObjectsWithoutExpectations]
class ProgressiveImageComponentTest extends TestCase
{
    public function testComponentProperties(): void
    {
        $metadataReader = $this->createMock(MetadataReaderInterface::class);
        $metadata = new ImageMetadata('hash', 800, 600);

        $metadataReader->expects($this->once())
            ->method('getMetadata')
            ->with('test.jpg')
            ->willReturn($metadata);

        $decorator = $this->createMock(PathDecoratorInterface::class);
        $decorator->expects($this->once())
            ->method('decorate')
            ->with('test.jpg')
            ->willReturn('decorated-test.jpg');
        $collector = $this->createMock(PreloadCollector::class);

        $component = new Image($metadataReader, [$decorator], null, $collector);
        $component->src = 'test.jpg';
        $component->postMount();

        $this->assertSame('hash', $component->getHash());
        $this->assertSame(800, $component->getWidth());
        $this->assertSame(600, $component->getHeight());
        $this->assertSame('decorated-test.jpg', $component->getDecoratedSrc());
    }

    public function testComponentWithNoMetadata(): void
    {
        $metadataReader = $this->createMock(MetadataReaderInterface::class);
        $metadataReader->expects($this->once())
            ->method('getMetadata')
            ->willThrowException(new PathResolutionException('Not found'));
        $collector = $this->createMock(PreloadCollector::class);

        $component = new Image($metadataReader, [], null, $collector);
        $component->src = 'test.jpg';
        $component->postMount();

        $this->assertNull($component->getHash());
        $this->assertNull($component->getWidth());
        $this->assertNull($component->getHeight());
        $this->assertSame('test.jpg', $component->getDecoratedSrc());
    }

    public function testComponentLogsWhenMetadataResolutionFails(): void
    {
        $metadataReader = $this->createMock(MetadataReaderInterface::class);
        $metadataReader->method('getMetadata')->willThrowException(new PathResolutionException('Not found'));
        $collector = $this->createMock(PreloadCollector::class);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $component = new Image($metadataReader, [], null, $collector, logger: $logger);
        $component->src = 'test.jpg';
        $component->postMount();

        $this->assertNull($component->getHash());
    }

    public function testPathDecoratorIterableIsOnlyConsumedOnceEvenThoughUsedTwice(): void
    {
        $decorator = new class implements PathDecoratorInterface {
            public function decorate(string $path, array $context = []): string
            {
                return $path;
            }

            public function getSize(string $path, array $context = []): ?array
            {
                return ['width' => 42, 'height' => 24];
            }
        };

        // A one-shot generator, as an application-provided iterable might be.
        $generator = (function () use ($decorator) {
            yield $decorator;
        })();

        $metadataReader = $this->createMock(MetadataReaderInterface::class);
        $metadataReader->method('getMetadata')->willReturn(new ImageMetadata('hash', 10, 10));
        $collector = $this->createMock(PreloadCollector::class);

        $component = new Image($metadataReader, $generator, null, $collector);
        $component->src = 'test.jpg';
        $component->postMount();

        $this->assertSame(42, $component->getWidth());
        $this->assertSame(24, $component->getHeight());
    }

    public function testPostMountDoesNotEmitWarningWhenMetadataIsNullAndResponsiveAttributesAreGenerated(): void
    {
        $metadataReader = $this->createMock(MetadataReaderInterface::class);
        $metadataReader->method('getMetadata')->willThrowException(new PathResolutionException('Not found'));
        $collector = new PreloadCollector(new RequestStack());

        $urlGenerator = $this->createMock(ResponsiveImageUrlGeneratorInterface::class);
        $urlGenerator->method('generateUrl')->willReturn('http://example.com/img.jpg');

        $responsiveAttributeGenerator = new ResponsiveAttributeGenerator(
            ['layouts' => ['default' => ['min_viewport' => 0, 'max_container' => null]], 'columns' => 12],
            [],
            [1, 2],
            $collector,
            $urlGenerator
        );

        $component = new Image($metadataReader, [], $responsiveAttributeGenerator, $collector);
        $component->src = 'test.jpg';
        $component->sizes = 'default:12';

        $warnings = [];
        set_error_handler(static function (int $errno, string $errstr) use (&$warnings): bool {
            $warnings[] = $errstr;

            return true;
        });
        try {
            $component->postMount();
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $warnings, 'postMount() must not read a property on a null $metadata.');
    }
}
