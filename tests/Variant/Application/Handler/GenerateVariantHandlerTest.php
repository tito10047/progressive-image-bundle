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

namespace Tito10047\ProgressiveImageBundle\Tests\Variant\Application\Handler;

use PHPUnit\Framework\TestCase;
use Tito10047\ProgressiveImageBundle\Tests\Variant\Double\FakeImageManipulator;
use Tito10047\ProgressiveImageBundle\Tests\Variant\Double\FakePostProcessor;
use Tito10047\ProgressiveImageBundle\Tests\Variant\Double\FakeSourceReader;
use Tito10047\ProgressiveImageBundle\Tests\Variant\Double\FrozenClock;
use Tito10047\ProgressiveImageBundle\Tests\Variant\Double\InMemoryGenerationLock;
use Tito10047\ProgressiveImageBundle\Tests\Variant\Double\InMemoryVariantStorage;
use Tito10047\ProgressiveImageBundle\Tests\Variant\Double\SpyDomainEventBus;
use Tito10047\ProgressiveImageBundle\Variant\Application\Command\GenerateVariant;
use Tito10047\ProgressiveImageBundle\Variant\Application\Handler\GenerateVariantHandler;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Event\VariantGenerated;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Event\VariantGenerationFailed;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Exception\SourceNotReadable;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\FilterChain;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\Thumbnail;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\Dimensions;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\GeneratedImage;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\OutputFormat;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\Quality;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\SourceImage;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\SourcePath;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\Variant;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\VariantSpec;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Port\PostProcessor;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Service\VariantIdHasher;

final class GenerateVariantHandlerTest extends TestCase
{
    private const int FAIL_MARKER_TTL = 300;

    private InMemoryVariantStorage $storage;
    private InMemoryGenerationLock $lock;
    private FakeSourceReader $sourceReader;
    private FakeImageManipulator $manipulator;
    private SpyDomainEventBus $eventBus;
    private FrozenClock $clock;
    private VariantIdHasher $hasher;

    protected function setUp(): void
    {
        $this->storage = new InMemoryVariantStorage();
        $this->lock = new InMemoryGenerationLock();
        $stream = fopen('php://memory', 'r');
        self::assertNotFalse($stream);
        $this->sourceReader = FakeSourceReader::returning(new SourceImage($stream, new Dimensions(1000, 1000), 'image/jpeg'));
        $this->manipulator = new FakeImageManipulator();
        $this->eventBus = new SpyDomainEventBus();
        $this->clock = new FrozenClock();
        $this->hasher = new VariantIdHasher('secret');
    }

    /**
     * @param iterable<PostProcessor> $postProcessors
     */
    private function makeHandler(iterable $postProcessors = []): GenerateVariantHandler
    {
        return new GenerateVariantHandler(
            $this->hasher,
            $this->lock,
            $this->storage,
            $this->sourceReader,
            $this->manipulator,
            $postProcessors,
            $this->eventBus,
            $this->clock,
            self::FAIL_MARKER_TTL
        );
    }

    private function makeCommand(): GenerateVariant
    {
        return new GenerateVariant(
            new SourcePath('uploads/hero.jpg'),
            new VariantSpec(FilterChain::of(Thumbnail::outbound(new Dimensions(200, 200))), OutputFormat::Webp, new Quality(82))
        );
    }

    public function testGeneratesWritesAndPublishesVariantGeneratedOnSuccess(): void
    {
        $command = $this->makeCommand();
        $variant = Variant::request($command->source, $command->spec, $this->hasher);

        ($this->makeHandler())($command);

        self::assertTrue($this->storage->exists($variant->path()));
        self::assertSame('processed-image-bytes', $this->storage->read($variant->path())->contents);
        self::assertCount(1, $this->eventBus->published());
        self::assertInstanceOf(VariantGenerated::class, $this->eventBus->published()[0]);
        self::assertFalse($this->lock->isHeld($variant->id), 'lock must be released after success');
    }

    public function testRunsSupportingPostProcessorsInOrder(): void
    {
        $command = $this->makeCommand();
        $webpProcessor = new FakePostProcessor(OutputFormat::Webp, 'webp-optimized');
        $avifProcessor = new FakePostProcessor(OutputFormat::Avif, 'avif-optimized');

        ($this->makeHandler([$webpProcessor, $avifProcessor]))($command);

        $variant = Variant::request($command->source, $command->spec, $this->hasher);
        self::assertSame('processed-image-bytes|webp-optimized', $this->storage->read($variant->path())->contents);
    }

    public function testIsIdempotentWhenVariantAlreadyExists(): void
    {
        $command = $this->makeCommand();
        $variant = Variant::request($command->source, $command->spec, $this->hasher);
        $this->storage->write($variant->path(), new GeneratedImage('already-there', OutputFormat::Webp));

        ($this->makeHandler())($command);

        self::assertSame('already-there', $this->storage->read($variant->path())->contents, 'must not overwrite an already-generated variant');
        self::assertCount(0, $this->eventBus->published());
        self::assertFalse($this->lock->isHeld($variant->id));
    }

    public function testIsANoOpWhenAnotherProcessHoldsTheLock(): void
    {
        $this->lock->markAlwaysBusy();
        $command = $this->makeCommand();

        ($this->makeHandler())($command);

        self::assertCount(0, $this->eventBus->published());
        $variant = Variant::request($command->source, $command->spec, $this->hasher);
        self::assertFalse($this->storage->exists($variant->path()));
    }

    public function testBacksOffWhenFailMarkerIsFresh(): void
    {
        $command = $this->makeCommand();
        $variant = Variant::request($command->source, $command->spec, $this->hasher);
        $this->storage->writeFailMarker($variant->path(), $this->clock->now());

        ($this->makeHandler())($command);

        self::assertFalse($this->storage->exists($variant->path()));
        self::assertCount(0, $this->eventBus->published());
        self::assertFalse($this->lock->isHeld($variant->id));
    }

    public function testRetriesWhenFailMarkerHasExpired(): void
    {
        $command = $this->makeCommand();
        $variant = Variant::request($command->source, $command->spec, $this->hasher);
        $this->storage->writeFailMarker($variant->path(), $this->clock->now());
        $this->clock->advanceBySeconds(self::FAIL_MARKER_TTL + 1);

        ($this->makeHandler())($command);

        self::assertTrue($this->storage->exists($variant->path()));
        self::assertCount(1, $this->eventBus->published());
    }

    public function testWritesFailMarkerPublishesFailureEventAndRethrowsOnError(): void
    {
        $command = $this->makeCommand();
        $variant = Variant::request($command->source, $command->spec, $this->hasher);
        $this->sourceReader = FakeSourceReader::failingWith(new SourceNotReadable('boom'));

        $this->expectException(SourceNotReadable::class);

        try {
            ($this->makeHandler())($command);
        } finally {
            self::assertNotNull($this->storage->failMarkerTimestamp($variant->path()));
            self::assertCount(1, $this->eventBus->published());
            self::assertInstanceOf(VariantGenerationFailed::class, $this->eventBus->published()[0]);
            self::assertFalse($this->lock->isHeld($variant->id), 'lock must be released even on failure');
        }
    }
}
