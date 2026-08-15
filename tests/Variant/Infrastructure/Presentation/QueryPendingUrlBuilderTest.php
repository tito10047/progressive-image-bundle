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
use Tito10047\ProgressiveImageBundle\Tests\Variant\Double\FakeUrlGenerator;
use Tito10047\ProgressiveImageBundle\Variant\Application\Query\ResolveVariantUrl;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\Dimensions;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\PointOfInterest;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\SourcePath;
use Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Presentation\UrlGenerator\QueryPendingUrlBuilder;

final class QueryPendingUrlBuilderTest extends TestCase
{
    private FakeUrlGenerator $urlGenerator;
    private QueryPendingUrlBuilder $builder;

    protected function setUp(): void
    {
        $this->urlGenerator = new FakeUrlGenerator();
        $this->builder = new QueryPendingUrlBuilder($this->urlGenerator);
    }

    public function testGeneratesThePgiVariantServeRouteWithSourceAndDimensions(): void
    {
        $this->builder->build(new ResolveVariantUrl(new SourcePath('uploads/hero.jpg'), 200, 100));

        self::assertSame('pgi_variant_serve', $this->urlGenerator->lastRoute);
        self::assertSame('uploads/hero.jpg', $this->urlGenerator->lastParameters['source']);
        self::assertSame(200, $this->urlGenerator->lastParameters['width']);
        self::assertSame(100, $this->urlGenerator->lastParameters['height']);
        self::assertArrayNotHasKey('filterSet', $this->urlGenerator->lastParameters);
        self::assertArrayNotHasKey('poiX', $this->urlGenerator->lastParameters);
        self::assertArrayNotHasKey('context', $this->urlGenerator->lastParameters);
    }

    public function testIncludesFilterSetWhenGiven(): void
    {
        $this->builder->build(new ResolveVariantUrl(new SourcePath('a.jpg'), 200, 100, 'thumbnail_square'));

        self::assertSame('thumbnail_square', $this->urlGenerator->lastParameters['filterSet']);
    }

    public function testIncludesPoiAndOriginalDimensionsWhenGiven(): void
    {
        $this->builder->build(new ResolveVariantUrl(
            new SourcePath('a.jpg'),
            200,
            100,
            null,
            new PointOfInterest(500, 300),
            new Dimensions(1000, 1000)
        ));

        self::assertSame(500, $this->urlGenerator->lastParameters['poiX']);
        self::assertSame(300, $this->urlGenerator->lastParameters['poiY']);
        self::assertSame(1000, $this->urlGenerator->lastParameters['origW']);
        self::assertSame(1000, $this->urlGenerator->lastParameters['origH']);
    }

    public function testEncodesContextAsJsonWhenNonEmpty(): void
    {
        $this->builder->build(new ResolveVariantUrl(new SourcePath('a.jpg'), 200, 100, context: ['filter' => 'circle']));

        self::assertSame('{"filter":"circle"}', $this->urlGenerator->lastParameters['context']);
    }

    public function testReturnsWhateverTheUrlGeneratorProduces(): void
    {
        $url = $this->builder->build(new ResolveVariantUrl(new SourcePath('a.jpg'), 200, 100));

        self::assertStringStartsWith('/pgi_variant_serve?', $url);
    }
}
