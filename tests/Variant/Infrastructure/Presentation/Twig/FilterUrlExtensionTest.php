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

namespace Tito10047\ProgressiveImageBundle\Tests\Variant\Infrastructure\Presentation\Twig;

use PHPUnit\Framework\TestCase;
use Tito10047\ProgressiveImageBundle\Tests\Variant\Double\FakeOriginalUrlResolver;
use Tito10047\ProgressiveImageBundle\Tests\Variant\Double\InMemoryVariantStorage;
use Tito10047\ProgressiveImageBundle\Tests\Variant\Double\SpyGenerationDispatcher;
use Tito10047\ProgressiveImageBundle\Variant\Application\Handler\ResolveFilterUrlHandler;
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
use Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Presentation\Twig\FilterUrlExtension;
use Twig\TwigFunction;

final class FilterUrlExtensionTest extends TestCase
{
    private InMemoryVariantStorage $storage;
    private VariantIdHasher $hasher;
    private VariantSpecFactory $specFactory;

    protected function setUp(): void
    {
        $this->storage = new InMemoryVariantStorage();
        $this->hasher = new VariantIdHasher('secret');
        $this->specFactory = new VariantSpecFactory(
            new FilterSetRegistry(['thumb_small' => ['filters' => ['thumbnail' => ['size' => [100, 100], 'mode' => 'outbound']]]], new FilterFactory()),
            new FilterFactory(),
            new AspectCropCalculator()
        );
    }

    private function makeExtension(): FilterUrlExtension
    {
        $handler = new ResolveFilterUrlHandler(
            $this->specFactory,
            $this->hasher,
            $this->storage,
            new PendingGenerationTracker(),
            new SpyGenerationDispatcher(),
            new FakeOriginalUrlResolver(),
        );

        return new FilterUrlExtension($handler);
    }

    public function testRegistersThePgiFilterTwigFunction(): void
    {
        $functions = $this->makeExtension()->getFunctions();

        self::assertCount(1, $functions);
        self::assertInstanceOf(TwigFunction::class, $functions[0]);
        self::assertSame('pgi_filter', $functions[0]->getName());
    }

    public function testResolveReturnsThePublicPathWhenTheVariantAlreadyExists(): void
    {
        $spec = $this->specFactory->createFromFilterSet('thumb_small');
        $variant = Variant::request(new SourcePath('uploads/hero.jpg'), $spec, $this->hasher);
        $this->storage->write($variant->path(), new GeneratedImage('bytes', OutputFormat::Jpeg));

        $url = $this->makeExtension()->resolve('uploads/hero.jpg', 'thumb_small');

        self::assertSame($this->storage->publicPath($variant->path()), $url);
    }

    public function testResolveFallsBackToTheOriginalUrlWhenTheVariantIsNotYetReady(): void
    {
        $url = $this->makeExtension()->resolve('uploads/hero.jpg', 'thumb_small');

        self::assertSame('/original/uploads/hero.jpg', $url);
    }
}
