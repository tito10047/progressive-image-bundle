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
use Tito10047\ProgressiveImageBundle\Tests\Variant\Double\InMemoryVariantStorage;
use Tito10047\ProgressiveImageBundle\Tests\Variant\Double\SpyGenerationDispatcher;
use Tito10047\ProgressiveImageBundle\Variant\Application\Handler\ResolveFilterUrlHandler;
use Tito10047\ProgressiveImageBundle\Variant\Application\Query\ResolveFilterUrl;
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

final class ResolveFilterUrlHandlerTest extends TestCase
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
            new FilterSetRegistry(['thumb_small' => ['filters' => ['thumbnail' => ['size' => [100, 100], 'mode' => 'outbound']]]], new FilterFactory()),
            new FilterFactory(),
            new AspectCropCalculator()
        );
    }

    private function makeHandler(): ResolveFilterUrlHandler
    {
        return new ResolveFilterUrlHandler(
            $this->specFactory,
            $this->hasher,
            $this->storage,
            $this->tracker,
            $this->dispatcher,
            new FakeOriginalUrlResolver(),
        );
    }

    private function makeQuery(): ResolveFilterUrl
    {
        return new ResolveFilterUrl(new SourcePath('uploads/hero.jpg'), 'thumb_small');
    }

    public function testHitReturnsThePublicPathWithoutDispatchingGeneration(): void
    {
        $query = $this->makeQuery();
        $spec = $this->specFactory->createFromFilterSet($query->filterSet, $query->context);
        $variant = Variant::request($query->source, $spec, $this->hasher);
        $this->storage->write($variant->path(), new GeneratedImage('bytes', OutputFormat::Jpeg));

        $resolved = ($this->makeHandler())($query);

        self::assertFalse($resolved->pending);
        self::assertSame($this->storage->publicPath($variant->path()), $resolved->url);
        self::assertCount(0, $this->dispatcher->dispatched());
        self::assertFalse($this->tracker->hasPending());
    }

    public function testMissReturnsOriginalUrlAndDispatchesGeneration(): void
    {
        $query = $this->makeQuery();

        $resolved = ($this->makeHandler())($query);

        self::assertTrue($resolved->pending);
        self::assertSame('/original/uploads/hero.jpg', $resolved->url);
        self::assertCount(1, $this->dispatcher->dispatched());
        self::assertTrue($this->tracker->hasPending());
    }

    public function testDispatchedCommandCarriesTheResolvedSourceAndSpec(): void
    {
        $query = $this->makeQuery();

        ($this->makeHandler())($query);

        $dispatched = $this->dispatcher->dispatched();
        self::assertSame('uploads/hero.jpg', $dispatched[0]->source->value);
    }

    public function testMemoizesWithinTheSameHandlerInstanceForTheSameVariant(): void
    {
        $handler = $this->makeHandler();
        $query = $this->makeQuery();

        $first = $handler($query);
        $second = $handler($query);

        self::assertEquals($first, $second);
        self::assertCount(1, $this->dispatcher->dispatched(), 'must not dispatch generation twice for the same VariantId within one handler lifetime');
    }

    public function testDifferentQueriesAreNotMemoizedTogether(): void
    {
        $handler = $this->makeHandler();

        $handler(new ResolveFilterUrl(new SourcePath('uploads/a.jpg'), 'thumb_small'));
        $handler(new ResolveFilterUrl(new SourcePath('uploads/b.jpg'), 'thumb_small'));

        self::assertCount(2, $this->dispatcher->dispatched());
    }
}
