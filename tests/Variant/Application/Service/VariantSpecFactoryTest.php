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

namespace Tito10047\ProgressiveImageBundle\Tests\Variant\Application\Service;

use PHPUnit\Framework\TestCase;
use Tito10047\ProgressiveImageBundle\Variant\Application\Service\FilterFactory;
use Tito10047\ProgressiveImageBundle\Variant\Application\Service\FilterSetRegistry;
use Tito10047\ProgressiveImageBundle\Variant\Application\Service\VariantSpecFactory;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Exception\InvalidFilterDefinition;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\Crop;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\Resize;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Filter\Thumbnail;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\Dimensions;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\OutputFormat;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\PointOfInterest;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\Quality;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Service\AspectCropCalculator;

final class VariantSpecFactoryTest extends TestCase
{
    /**
     * @param array<string, array<string, mixed>> $filterSets
     * @param array<string, mixed>                $imageConfigs
     */
    private function makeFactory(array $filterSets = [], array $imageConfigs = []): VariantSpecFactory
    {
        return new VariantSpecFactory(
            new FilterSetRegistry($filterSets, new FilterFactory()),
            new FilterFactory(),
            new AspectCropCalculator(),
            $imageConfigs,
            OutputFormat::Jpeg,
            new Quality(85)
        );
    }

    public function testCreateWithFilterSetPrependsItsFiltersBeforeTheSizingThumbnail(): void
    {
        $factory = $this->makeFactory([
            'my_filter' => ['filters' => ['resize' => ['size' => [100, 50]]]],
        ]);

        $spec = $factory->create(200, 150, 'my_filter');

        $filters = iterator_to_array($spec->filters, false);
        self::assertCount(2, $filters);
        self::assertInstanceOf(Resize::class, $filters[0]);
        self::assertInstanceOf(Thumbnail::class, $filters[1]);
        self::assertSame(['thumbnail' => ['w' => 200, 'h' => 150, 'mode' => 'outbound']], $filters[1]->canonical());
    }

    public function testCreateWithoutFilterSetProducesJustTheSizingThumbnail(): void
    {
        $spec = $this->makeFactory()->create(300, 200);

        $filters = iterator_to_array($spec->filters, false);
        self::assertCount(1, $filters);
        self::assertSame(['thumbnail' => ['w' => 300, 'h' => 200, 'mode' => 'outbound']], $filters[0]->canonical());
    }

    public function testThrowsForUnknownFilterSetName(): void
    {
        $this->expectException(InvalidFilterDefinition::class);

        $this->makeFactory()->create(200, 150, 'does_not_exist');
    }

    /**
     * Pixel-parity golden case ported from the old runtime-filter generator's test suite.
     * POI (500,500) on a 1000x1000 image, target 200x100 → crop (0,250) size (1000,500), then thumbnail inset.
     */
    public function testCreateWithPointInterestProducesCropThenInsetThumbnail(): void
    {
        $spec = $this->makeFactory()->create(
            200,
            100,
            null,
            new PointOfInterest(500, 500),
            new Dimensions(1000, 1000)
        );

        $filters = iterator_to_array($spec->filters, false);
        self::assertCount(2, $filters);
        self::assertInstanceOf(Crop::class, $filters[0]);
        self::assertSame(['crop' => ['x' => 0, 'y' => 250, 'w' => 1000, 'h' => 500]], $filters[0]->canonical());
        self::assertSame(['thumbnail' => ['w' => 200, 'h' => 100, 'mode' => 'inset']], $filters[1]->canonical());
    }

    /**
     * Ported from the old runtime-filter generator's edge-clamping test.
     */
    public function testCreateWithPointInterestAtEdgesClampsCropStart(): void
    {
        $factory = $this->makeFactory();

        $topLeft = $factory->create(200, 100, null, new PointOfInterest(0, 0), new Dimensions(1000, 1000));
        $topLeftCrop = iterator_to_array($topLeft->filters, false)[0];
        self::assertSame(['crop' => ['x' => 0, 'y' => 0, 'w' => 1000, 'h' => 500]], $topLeftCrop->canonical());

        $bottomRight = $factory->create(200, 100, null, new PointOfInterest(1000, 1000), new Dimensions(1000, 1000));
        $bottomRightCrop = iterator_to_array($bottomRight->filters, false)[0];
        self::assertSame(['crop' => ['x' => 0, 'y' => 500, 'w' => 1000, 'h' => 500]], $bottomRightCrop->canonical());
    }

    /**
     * Regression test for commit f3e55c3 (POI crop/thumbnail ordering when imageConfigs
     * already has a thumbnail). VariantSpecFactory strips any pre-existing crop/thumbnail
     * from the merged chain before appending the POI-driven pair, so the bug is now
     * impossible to reintroduce by construction rather than by careful array-key ordering.
     */
    public function testCropAlwaysPrecedesThumbnailEvenWhenImageConfigsDeclaresThumbnailFirst(): void
    {
        $factory = $this->makeFactory([], [
            'filters' => ['thumbnail' => ['size' => [100, 100], 'mode' => 'outbound']],
        ]);

        $spec = $factory->create(200, 100, null, new PointOfInterest(500, 500), new Dimensions(1000, 1000));

        $filters = iterator_to_array($spec->filters, false);
        self::assertCount(2, $filters, 'the imageConfigs thumbnail must be replaced, not duplicated');
        self::assertInstanceOf(Crop::class, $filters[0]);
        self::assertInstanceOf(Thumbnail::class, $filters[1]);
    }

    public function testImageConfigsQualityOverridesTheDefault(): void
    {
        $spec = $this->makeFactory([], ['quality' => 75])->create(200, 150);

        self::assertSame(75, $spec->quality->value);
    }

    public function testContextFormatOverridesFilterSetFormat(): void
    {
        $factory = $this->makeFactory([
            'hero' => ['filters' => [], 'format' => 'jpeg', 'quality' => 90],
        ]);

        $spec = $factory->create(200, 150, 'hero', context: ['format' => 'avif']);

        self::assertSame(OutputFormat::Avif, $spec->format);
        self::assertSame(90, $spec->quality->value, 'quality was not overridden by context, filter set value must survive the merge');
    }

    public function testDefaultsAreUsedWhenNothingOverridesFormatOrQuality(): void
    {
        $spec = $this->makeFactory()->create(200, 150);

        self::assertSame(OutputFormat::Jpeg, $spec->format);
        self::assertSame(85, $spec->quality->value);
    }

    /**
     * array_replace_recursive() merges two indexed (list) arrays element-by-numeric-index,
     * not wholesale — overriding only the width (a one-element list) would silently keep
     * the filter set's original height instead of failing loudly on the now-invalid pair.
     */
    public function testContextResizeSizeFullyReplacesFilterSetSizeInsteadOfMergingByIndex(): void
    {
        $factory = $this->makeFactory([
            'hero' => ['filters' => ['resize' => ['size' => [800, 600]]]],
        ]);

        $this->expectException(InvalidFilterDefinition::class);

        $factory->create(200, 150, 'hero', context: ['filters' => ['resize' => ['size' => [400]]]]);
    }

    public function testContextResizeSizeThatDoesFullyOverrideBothDimensionsIsUsedAsIs(): void
    {
        $factory = $this->makeFactory([
            'hero' => ['filters' => ['resize' => ['size' => [800, 600]]]],
        ]);

        $spec = $factory->create(200, 150, 'hero', context: ['filters' => ['resize' => ['size' => [400, 300]]]]);

        $filters = iterator_to_array($spec->filters, false);
        self::assertSame(['resize' => ['w' => 400, 'h' => 300]], $filters[0]->canonical());
    }

    /**
     * createFromFilterSet() (used by pgi_filter() and the on-the-fly resolve route) must
     * trust the filter set's own sizing filters (or lack thereof) exactly as configured —
     * unlike create(), it never force-injects a Thumbnail/Crop pair. A filter set that
     * defines its own thumbnail keeps exactly that filter, unmodified.
     */
    public function testCreateFromFilterSetKeepsTheFilterSetsOwnThumbnailUnmodified(): void
    {
        $factory = $this->makeFactory([
            'thumb_small' => ['filters' => ['thumbnail' => ['size' => [100, 100], 'mode' => 'outbound']]],
        ]);

        $spec = $factory->createFromFilterSet('thumb_small');

        $filters = iterator_to_array($spec->filters, false);
        self::assertCount(1, $filters);
        self::assertInstanceOf(Thumbnail::class, $filters[0]);
        self::assertSame(['thumbnail' => ['w' => 100, 'h' => 100, 'mode' => 'outbound']], $filters[0]->canonical());
    }

    /**
     * A filter set with no sizing filter at all (e.g. just a watermark) must produce a
     * chain with no sizing filter — createFromFilterSet() never force-injects one, unlike
     * create().
     */
    public function testCreateFromFilterSetWithNoSizingFilterProducesNoSizingFilter(): void
    {
        $factory = $this->makeFactory([
            'grayscale_only' => ['filters' => ['resize' => ['size' => [50, 50]]]],
        ]);

        $spec = $factory->createFromFilterSet('grayscale_only');

        $filters = iterator_to_array($spec->filters, false);
        self::assertCount(1, $filters);
        self::assertInstanceOf(Resize::class, $filters[0]);
    }

    public function testCreateFromFilterSetThrowsForUnknownFilterSetName(): void
    {
        $this->expectException(InvalidFilterDefinition::class);

        $this->makeFactory()->createFromFilterSet('does_not_exist');
    }

    public function testCreateFromFilterSetAppliesImageConfigsAndContextInTheSameMergeOrderAsCreate(): void
    {
        $factory = $this->makeFactory([
            'hero' => ['filters' => [], 'format' => 'jpeg', 'quality' => 90],
        ]);

        $spec = $factory->createFromFilterSet('hero', ['format' => 'avif']);

        self::assertSame(OutputFormat::Avif, $spec->format);
        self::assertSame(90, $spec->quality->value);
    }

    /**
     * Regression guard: create()'s own forced-sizing behavior must be completely unaffected
     * by createFromFilterSet() existing — both methods share the merge/parseFilters
     * machinery, so this pins create() still always appends its own Thumbnail.
     */
    public function testCreateStillForcesItsOwnSizingThumbnailRegardlessOfCreateFromFilterSet(): void
    {
        $factory = $this->makeFactory([
            'thumb_small' => ['filters' => ['thumbnail' => ['size' => [100, 100], 'mode' => 'outbound']]],
        ]);

        $spec = $factory->create(300, 300, 'thumb_small');

        $filters = iterator_to_array($spec->filters, false);
        self::assertCount(1, $filters);
        self::assertSame(['thumbnail' => ['w' => 300, 'h' => 300, 'mode' => 'outbound']], $filters[0]->canonical());
    }
}
