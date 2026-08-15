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

namespace Tito10047\ProgressiveImageBundle\Tests\Variant\Infrastructure\Presentation;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Tito10047\ProgressiveImageBundle\DTO\ImageMetadata;
use Tito10047\ProgressiveImageBundle\Service\MetadataReaderInterface;
use Tito10047\ProgressiveImageBundle\Tests\Variant\Double\FakeOriginalUrlResolver;
use Tito10047\ProgressiveImageBundle\Tests\Variant\Double\FakeUrlSigner;
use Tito10047\ProgressiveImageBundle\Tests\Variant\Double\InMemoryVariantStorage;
use Tito10047\ProgressiveImageBundle\Tests\Variant\Double\SpyGenerationDispatcher;
use Tito10047\ProgressiveImageBundle\Variant\Application\Handler\ResolveVariantUrlHandler;
use Tito10047\ProgressiveImageBundle\Variant\Application\Query\PendingFallbackStrategy;
use Tito10047\ProgressiveImageBundle\Variant\Application\Service\FilterFactory;
use Tito10047\ProgressiveImageBundle\Variant\Application\Service\FilterSetRegistry;
use Tito10047\ProgressiveImageBundle\Variant\Application\Service\PendingGenerationTracker;
use Tito10047\ProgressiveImageBundle\Variant\Application\Service\VariantSpecFactory;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\Crop;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\Resize;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\OutputFormat;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Service\AspectCropCalculator;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Service\VariantIdHasher;
use Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Presentation\UrlGenerator\VariantResponsiveImageUrlGenerator;

final class VariantResponsiveImageUrlGeneratorTest extends TestCase
{
    private InMemoryVariantStorage $storage;
    private SpyGenerationDispatcher $dispatcher;
    private VariantIdHasher $hasher;
    private VariantSpecFactory $specFactory;

    protected function setUp(): void
    {
        $this->storage = new InMemoryVariantStorage();
        $this->dispatcher = new SpyGenerationDispatcher();
        $this->hasher = new VariantIdHasher('secret');
        $this->specFactory = new VariantSpecFactory(new FilterSetRegistry([], new FilterFactory()), new FilterFactory(), new AspectCropCalculator());
    }

    /**
     * @param list<string>          $negotiateFormats
     * @param array<string, int>    $qualityByFormat
     */
    private function makeGenerator(
        MetadataReaderInterface $metadataReader,
        ?RequestStack $requestStack = null,
        array $negotiateFormats = [],
        array $qualityByFormat = [],
    ): VariantResponsiveImageUrlGenerator {
        $resolveHandler = new ResolveVariantUrlHandler(
            $this->specFactory,
            $this->hasher,
            $this->storage,
            new PendingGenerationTracker(),
            $this->dispatcher,
            new FakeOriginalUrlResolver(),
            null,
            new FakeUrlSigner(),
            PendingFallbackStrategy::Original
        );

        return new VariantResponsiveImageUrlGenerator(
            $resolveHandler,
            $metadataReader,
            $requestStack ?? new RequestStack(),
            $negotiateFormats,
            $qualityByFormat
        );
    }

    public function testReturnsTheResolvedUrl(): void
    {
        $generator = $this->makeGenerator($this->createStub(MetadataReaderInterface::class));

        $url = $generator->generateUrl('uploads/hero.jpg', 200, 200);

        self::assertSame('/original/uploads/hero.jpg', $url);
    }

    public function testDefaultsHeightToWidthWhenNotGiven(): void
    {
        $generator = $this->makeGenerator($this->createStub(MetadataReaderInterface::class));

        $generator->generateUrl('uploads/hero.jpg', 200, null);

        $filters = iterator_to_array($this->dispatcher->dispatched()[0]->spec->filters, false);
        self::assertSame(['thumbnail' => ['w' => 200, 'h' => 200, 'mode' => 'outbound']], $filters[0]->canonical());
    }

    public function testExtractsFilterFromContextAsTheFilterSetNameAndKeepsTheRestOfContext(): void
    {
        $this->specFactory = new VariantSpecFactory(
            new FilterSetRegistry(['sq' => ['filters' => ['resize' => ['size' => [10, 10]]]]], new FilterFactory()),
            new FilterFactory(),
            new AspectCropCalculator()
        );
        $generator = $this->makeGenerator($this->createStub(MetadataReaderInterface::class));

        $generator->generateUrl('uploads/hero.jpg', 200, 200, null, ['filter' => 'sq']);

        $filters = iterator_to_array($this->dispatcher->dispatched()[0]->spec->filters, false);
        self::assertInstanceOf(Resize::class, $filters[0]);
    }

    public function testResolvesOriginalDimensionsViaMetadataReaderWhenPointInterestGiven(): void
    {
        $metadataReader = $this->createMock(MetadataReaderInterface::class);
        $metadataReader->expects(self::once())
            ->method('getMetadata')
            ->with('uploads/hero.jpg')
            ->willReturn(new ImageMetadata('hash', 1000, 1000));

        $generator = $this->makeGenerator($metadataReader);

        $generator->generateUrl('uploads/hero.jpg', 200, 100, '500x500');

        $filters = iterator_to_array($this->dispatcher->dispatched()[0]->spec->filters, false);
        self::assertInstanceOf(Crop::class, $filters[0]);
    }

    public function testDoesNotCallMetadataReaderWithoutPointInterest(): void
    {
        $metadataReader = $this->createMock(MetadataReaderInterface::class);
        $metadataReader->expects(self::never())->method('getMetadata');

        $this->makeGenerator($metadataReader)->generateUrl('uploads/hero.jpg', 200, 200);
    }

    public function testNegotiatesTheFirstAcceptedFormatAndItsConfiguredQuality(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/', server: ['HTTP_ACCEPT' => 'image/avif,image/webp,*/*']));

        $generator = $this->makeGenerator(
            $this->createStub(MetadataReaderInterface::class),
            $requestStack,
            ['avif', 'webp'],
            ['avif' => 55, 'webp' => 82]
        );

        $generator->generateUrl('uploads/hero.jpg', 200, 200);

        $spec = $this->dispatcher->dispatched()[0]->spec;
        self::assertSame(OutputFormat::Avif, $spec->format);
        self::assertSame(55, $spec->quality->value);
    }

    public function testFallsBackToDefaultFormatWhenNothingNegotiated(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/', server: ['HTTP_ACCEPT' => 'text/html']));

        $generator = $this->makeGenerator(
            $this->createStub(MetadataReaderInterface::class),
            $requestStack,
            ['avif', 'webp']
        );

        $generator->generateUrl('uploads/hero.jpg', 200, 200);

        self::assertSame(OutputFormat::Jpeg, $this->dispatcher->dispatched()[0]->spec->format);
    }

    public function testExplicitContextFormatOverridesNegotiation(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/', server: ['HTTP_ACCEPT' => 'image/avif']));

        $generator = $this->makeGenerator(
            $this->createStub(MetadataReaderInterface::class),
            $requestStack,
            ['avif']
        );

        $generator->generateUrl('uploads/hero.jpg', 200, 200, null, ['format' => 'png']);

        self::assertSame(OutputFormat::Png, $this->dispatcher->dispatched()[0]->spec->format);
    }
}
