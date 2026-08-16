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
use Tito10047\ProgressiveImageBundle\Variant\Domain\Exception\InvalidFilterDefinition;

final class FilterSetRegistryTest extends TestCase
{
    public function testHasReflectsKnownFilterSets(): void
    {
        $registry = new FilterSetRegistry([
            'thumbnail_square' => ['filters' => ['thumbnail' => ['size' => [200, 200], 'mode' => 'outbound']]],
        ], new FilterFactory());

        self::assertTrue($registry->has('thumbnail_square'));
        self::assertFalse($registry->has('unknown_set'));
    }

    public function testRawFilterSetReturnsTheOriginalDefinition(): void
    {
        $definition = ['filters' => ['thumbnail' => ['size' => [200, 200], 'mode' => 'outbound']], 'format' => 'webp', 'quality' => 90];
        $registry = new FilterSetRegistry(['thumbnail_square' => $definition], new FilterFactory());

        self::assertSame($definition, $registry->rawFilterSet('thumbnail_square'));
    }

    public function testRawFilterSetThrowsForUnknownName(): void
    {
        $registry = new FilterSetRegistry([], new FilterFactory());

        $this->expectException(InvalidFilterDefinition::class);

        $registry->rawFilterSet('missing');
    }

    public function testConstructorFailsFastOnInvalidFilterSetDefinition(): void
    {
        $this->expectException(InvalidFilterDefinition::class);

        new FilterSetRegistry([
            'broken' => ['filters' => ['sepia' => []]],
        ], new FilterFactory());
    }

    public function testConstructorFailsFastOnMalformedFilterOptions(): void
    {
        $this->expectException(InvalidFilterDefinition::class);

        new FilterSetRegistry([
            'broken' => ['filters' => ['thumbnail' => ['mode' => 'outbound']]],
        ], new FilterFactory());
    }

    public function testNamesReturnsEveryConfiguredFilterSetName(): void
    {
        $registry = new FilterSetRegistry([
            'thumbnail_square' => ['filters' => []],
            'hero' => ['filters' => []],
        ], new FilterFactory());

        self::assertSame(['thumbnail_square', 'hero'], $registry->names());
    }

    public function testNamesIsEmptyForAnEmptyRegistry(): void
    {
        self::assertSame([], (new FilterSetRegistry([], new FilterFactory()))->names());
    }
}
