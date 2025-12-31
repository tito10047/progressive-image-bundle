<?php

namespace Tito10047\ProgressiveImageBundle\Tests\Functional;

use Liip\ImagineBundle\Imagine\Filter\FilterConfiguration;
use Tito10047\ProgressiveImageBundle\Tests\Integration\PGITestCase;

class PrependExtensionTest extends PGITestCase
{
    public function testLiipImagineConfigIsPrepended(): void
    {
        self::bootKernel([
            "progressive_image" => [
                'responsive_strategy' => [
                    'breakpoints' => [
                        'sm' => 480,
                        'md' => 800,
                    ],
                ],
            ],
            "liip_imagine" => [
                'filter_sets' => [
                    'preview2_big' => [
                        'quality' => 75,
                        'filters' => [
                            'thumbnail' => ['size' => [500, 500], 'mode' => 'outbound'],
                        ],
                        'post_processors' => [
                            'cwebp' => ['q' => 75, 'm' => 6],
                        ],
                    ],
                    'preview2_small' => [
                        'filters' => [
                            'thumbnail' => ['size' => [100, 100]],
                        ],
                    ],
                ],
            ],
        ]);

        $filterConfig = self::getContainer()->get('liip_imagine.filter.configuration');
        $this->assertInstanceOf(FilterConfiguration::class, $filterConfig);

        $allFilters = $filterConfig->all();

        // Pôvodné filtre
        $this->assertArrayHasKey('cache', $allFilters);
        $this->assertArrayHasKey('preview2_big', $allFilters);
        $this->assertArrayHasKey('preview2_small', $allFilters);

        // Dogenerované filtre pre preview_big
        $this->assertArrayHasKey('preview2_big_sm', $allFilters);
        $this->assertArrayHasKey('preview2_big_md', $allFilters);

        // Dogenerované filtre pre preview_small
        $this->assertArrayHasKey('preview2_small_sm', $allFilters);
        $this->assertArrayHasKey('preview2_small_md', $allFilters);

        // Kontrola hodnôt dogenerovaných filtrov
        $smFilter = $filterConfig->get('preview2_big_sm');
        $this->assertEquals(75, $smFilter['quality']);
        $this->assertEquals([480, 480], $smFilter['filters']['thumbnail']['size']);
        $this->assertEquals('outbound', $smFilter['filters']['thumbnail']['mode']);
        $this->assertEquals(['q' => 75, 'm' => 6], $smFilter['post_processors']['cwebp']);

        $mdFilter = $filterConfig->get('preview2_big_md');
        $this->assertEquals(75, $mdFilter['quality']);
        $this->assertEquals([800, 800], $mdFilter['filters']['thumbnail']['size']);

        $smallSmFilter = $filterConfig->get('preview2_small_sm');
        $this->assertEquals([480, 480], $smallSmFilter['filters']['thumbnail']['size']);
    }

    public function testAspectRatioIsPreserved(): void
    {
        self::bootKernel([
            "progressive_image" => [
                'responsive_strategy' => [
                    'breakpoints' => [
                        'sm' => 480,
                    ],
                ],
            ],
            "liip_imagine" => [
                'filter_sets' => [
                    'wide_format' => [
                        'filters' => [
                            'thumbnail' => ['size' => [1600, 900], 'mode' => 'outbound'],
                        ],
                    ],
                    'tall_format' => [
                        'filters' => [
                            'thumbnail' => ['size' => [300, 600], 'mode' => 'outbound'],
                        ],
                    ],
                ],
            ],
        ]);

        $filterConfig = self::getContainer()->get('liip_imagine.filter.configuration');
        
        // 16:9 ratio -> 480:270
        $wideSm = $filterConfig->get('wide_format_sm');
        $this->assertEquals([480, 270], $wideSm['filters']['thumbnail']['size']);

        // 1:2 ratio -> 480:960
        $tallSm = $filterConfig->get('tall_format_sm');
        $this->assertEquals([480, 960], $tallSm['filters']['thumbnail']['size']);
    }
}
