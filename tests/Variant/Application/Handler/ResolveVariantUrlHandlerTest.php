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
use Tito10047\ProgressiveImageBundle\Tests\Variant\Double\FakeOriginalUrlResolver;
use Tito10047\ProgressiveImageBundle\Tests\Variant\Double\FakePendingUrlBuilder;
use Tito10047\ProgressiveImageBundle\Tests\Variant\Double\FakeUrlSigner;
use Tito10047\ProgressiveImageBundle\Tests\Variant\Double\InMemoryVariantStorage;
use Tito10047\ProgressiveImageBundle\Tests\Variant\Double\SpyGenerationDispatcher;
use Tito10047\ProgressiveImageBundle\Variant\Application\Handler\ResolveVariantUrlHandler;
use Tito10047\ProgressiveImageBundle\Variant\Application\Query\PendingFallbackStrategy;
use Tito10047\ProgressiveImageBundle\Variant\Application\Query\ResolveVariantUrl;
use Tito10047\ProgressiveImageBundle\Variant\Application\Service\FilterFactory;
use Tito10047\ProgressiveImageBundle\Variant\Application\Service\FilterSetRegistry;
use Tito10047\ProgressiveImageBundle\Variant\Application\Service\PendingGenerationTracker;
use Tito10047\ProgressiveImageBundle\Variant\Application\Service\VariantSpecFactory;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\GeneratedImage;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\OutputFormat;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\SourcePath;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\Variant;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Service\AspectCropCalculator;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Service\VariantIdHasher;

final class ResolveVariantUrlHandlerTest extends TestCase
{
    private InMemoryVariantStorage $storage;
    private PendingGenerationTracker $tracker;
    private SpyGenerationDispatcher $dispatcher;
    private VariantIdHasher $hasher;
    private VariantSpecFactory $specFactory;

    protected function setUp(): void
    {
        $this->storage = new InMemoryVariantStorage();
        $this->tracker = new PendingGenerationTracker();
        $this->dispatcher = new SpyGenerationDispatcher();
        $this->hasher = new VariantIdHasher('secret');
        $this->specFactory = new VariantSpecFactory(
            new FilterSetRegistry([], new FilterFactory()),
            new FilterFactory(),
            new AspectCropCalculator()
        );
    }

    private function makeHandler(PendingFallbackStrategy $fallback = PendingFallbackStrategy::Original): ResolveVariantUrlHandler
    {
        return new ResolveVariantUrlHandler(
            $this->specFactory,
            $this->hasher,
            $this->storage,
            $this->tracker,
            $this->dispatcher,
            new FakeOriginalUrlResolver(),
            new FakePendingUrlBuilder(),
            new FakeUrlSigner(),
            $fallback
        );
    }

    private function makeQuery(): ResolveVariantUrl
    {
        return new ResolveVariantUrl(new SourcePath('uploads/hero.jpg'), 200, 200);
    }

    public function testHitReturnsThePublicPathWithoutDispatchingGeneration(): void
    {
        $query = $this->makeQuery();
        $spec = $this->specFactory->create($query->width, $query->height, $query->filterSet, $query->poi, $query->originalDimensions, $query->context);
        $variant = Variant::request($query->source, $spec, $this->hasher);
        $this->storage->write($variant->path(), new GeneratedImage('bytes', OutputFormat::Jpeg));

        $resolved = ($this->makeHandler())($query);

        self::assertFalse($resolved->pending);
        self::assertSame($this->storage->publicPath($variant->path()), $resolved->url);
        self::assertCount(0, $this->dispatcher->dispatched());
        self::assertFalse($this->tracker->hasPending());
    }

    public function testMissWithOriginalFallbackReturnsSourceUrlAndDispatchesGeneration(): void
    {
        $query = $this->makeQuery();

        $resolved = ($this->makeHandler(PendingFallbackStrategy::Original))($query);

        self::assertTrue($resolved->pending);
        self::assertSame('/original/uploads/hero.jpg', $resolved->url);
        self::assertCount(1, $this->dispatcher->dispatched());
        self::assertTrue($this->tracker->hasPending());
    }

    public function testMissWithWaitFallbackReturnsSignedWaitUrl(): void
    {
        $query = $this->makeQuery();

        $resolved = ($this->makeHandler(PendingFallbackStrategy::Wait))($query);

        self::assertTrue($resolved->pending);
        self::assertSame('/wait/uploads/hero.jpg?signed=1', $resolved->url);
        self::assertCount(1, $this->dispatcher->dispatched());
    }

    public function testDispatchedCommandCarriesTheResolvedSourceAndSpec(): void
    {
        $query = $this->makeQuery();

        ($this->makeHandler())($query);

        $dispatched = $this->dispatcher->dispatched();
        self::assertSame('uploads/hero.jpg', $dispatched[0]->source->value);
        self::assertSame(85, $dispatched[0]->spec->quality->value, 'the default quality, since no filter set/context overrides it');
    }

    public function testMemoizesWithinTheSameHandlerInstanceForTheSameVariant(): void
    {
        $handler = $this->makeHandler(PendingFallbackStrategy::Original);
        $query = $this->makeQuery();

        $first = $handler($query);
        $second = $handler($query);

        self::assertEquals($first, $second);
        self::assertCount(1, $this->dispatcher->dispatched(), 'must not dispatch generation twice for the same VariantId within one handler lifetime');
    }

    public function testDifferentQueriesAreNotMemoizedTogether(): void
    {
        $handler = $this->makeHandler(PendingFallbackStrategy::Original);

        $handler(new ResolveVariantUrl(new SourcePath('uploads/a.jpg'), 200, 200));
        $handler(new ResolveVariantUrl(new SourcePath('uploads/b.jpg'), 200, 200));

        self::assertCount(2, $this->dispatcher->dispatched());
    }
}
