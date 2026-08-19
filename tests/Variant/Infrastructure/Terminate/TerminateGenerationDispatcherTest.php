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

namespace Tito10047\ProgressiveImageBundle\Tests\Variant\Infrastructure\Terminate;

use PHPUnit\Framework\TestCase;
use Tito10047\ProgressiveImageBundle\Tests\Variant\Double\FakeImageManipulator;
use Tito10047\ProgressiveImageBundle\Tests\Variant\Double\FakeSourceReader;
use Tito10047\ProgressiveImageBundle\Tests\Variant\Double\FrozenClock;
use Tito10047\ProgressiveImageBundle\Tests\Variant\Double\InMemoryGenerationLock;
use Tito10047\ProgressiveImageBundle\Tests\Variant\Double\InMemoryVariantStorage;
use Tito10047\ProgressiveImageBundle\Tests\Variant\Double\SpyDomainEventBus;
use Tito10047\ProgressiveImageBundle\Variant\Application\Command\GenerateVariant;
use Tito10047\ProgressiveImageBundle\Variant\Application\Handler\GenerateVariantHandler;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Exception\SourceNotReadable;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\FilterChain;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\Thumbnail;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\Dimensions;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\OutputFormat;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\Quality;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\SourceImage;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\SourcePath;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\Variant;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\VariantSpec;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Port\SourceReader;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Service\VariantIdHasher;
use Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Terminate\TerminateGenerationDispatcher;

final class TerminateGenerationDispatcherTest extends TestCase
{
    private InMemoryVariantStorage $storage;
    private VariantIdHasher $hasher;
    private TerminateGenerationDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->storage = new InMemoryVariantStorage();
        $this->hasher = new VariantIdHasher('secret');
        $stream = fopen('php://memory', 'r');
        self::assertNotFalse($stream);

        $handler = new GenerateVariantHandler(
            $this->hasher,
            new InMemoryGenerationLock(),
            $this->storage,
            FakeSourceReader::returning(new SourceImage($stream, new Dimensions(100, 100), 'image/jpeg')),
            new FakeImageManipulator(),
            [],
            new SpyDomainEventBus(),
            new FrozenClock()
        );

        $this->dispatcher = new TerminateGenerationDispatcher($handler);
    }

    /**
     * @return array{SourcePath, VariantSpec, Variant}
     */
    private function makeVariant(string $path): array
    {
        $source = new SourcePath($path);
        $spec = new VariantSpec(FilterChain::of(Thumbnail::outbound(new Dimensions(200, 200))), OutputFormat::Webp, new Quality(82));

        return [$source, $spec, Variant::request($source, $spec, $this->hasher)];
    }

    public function testDispatchDoesNotRunGenerationImmediately(): void
    {
        [$source, $spec, $variant] = $this->makeVariant('uploads/hero.jpg');

        $this->dispatcher->dispatch(new GenerateVariant($source, $spec));

        self::assertFalse($this->storage->exists($variant->path()));
    }

    public function testOnTerminateRunsAllQueuedGenerations(): void
    {
        [$sourceA, $specA, $variantA] = $this->makeVariant('uploads/a.jpg');
        [$sourceB, $specB, $variantB] = $this->makeVariant('uploads/b.jpg');
        $this->dispatcher->dispatch(new GenerateVariant($sourceA, $specA));
        $this->dispatcher->dispatch(new GenerateVariant($sourceB, $specB));

        $this->dispatcher->onTerminate();

        self::assertTrue($this->storage->exists($variantA->path()));
        self::assertTrue($this->storage->exists($variantB->path()));
    }

    public function testOnTerminateProcessesAllQueuedCommandsEvenWhenAnEarlierOneFails(): void
    {
        $failingSourceReader = new class implements SourceReader {
            public function read(SourcePath $path): SourceImage
            {
                if ('uploads/broken.jpg' === $path->value) {
                    throw new SourceNotReadable('broken');
                }

                $stream = fopen('php://memory', 'r') ?: throw new \RuntimeException('could not open php://memory');

                return new SourceImage($stream, new Dimensions(100, 100), 'image/jpeg');
            }
        };

        $handler = new GenerateVariantHandler(
            $this->hasher,
            new InMemoryGenerationLock(),
            $this->storage,
            $failingSourceReader,
            new FakeImageManipulator(),
            [],
            new SpyDomainEventBus(),
            new FrozenClock()
        );

        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $logger->expects(self::once())->method('warning');

        $dispatcher = new TerminateGenerationDispatcher($handler, $logger);

        [$sourceBroken, $specBroken] = $this->makeVariant('uploads/broken.jpg');
        [$sourceOk, $specOk, $variantOk] = $this->makeVariant('uploads/ok.jpg');
        $dispatcher->dispatch(new GenerateVariant($sourceBroken, $specBroken));
        $dispatcher->dispatch(new GenerateVariant($sourceOk, $specOk));

        $dispatcher->onTerminate();

        self::assertTrue($this->storage->exists($variantOk->path()), 'the second (healthy) queued command must still run after the first one failed');
    }

    public function testOnTerminateClearsTheQueue(): void
    {
        [$source, $spec] = $this->makeVariant('uploads/hero.jpg');
        $this->dispatcher->dispatch(new GenerateVariant($source, $spec));

        $this->dispatcher->onTerminate();
        $this->storage->delete(Variant::request($source, $spec, $this->hasher)->path());
        $this->dispatcher->onTerminate();

        self::assertFalse($this->storage->exists(Variant::request($source, $spec, $this->hasher)->path()), 'a second onTerminate() must not re-run an already-flushed queue');
    }
}
