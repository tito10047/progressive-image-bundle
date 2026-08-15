<?php

/*
 * This file is part of the Progressive Image Bundle.
 *
 * (c) Jozef Môstka <https://github.com/tito10047/progressive-image-bundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tito10047\ProgressiveImageBundle\Tests\Unit\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;
use Tito10047\ProgressiveImageBundle\DependencyInjection\Configuration;

class ConfigurationTest extends TestCase
{
    public function testDefaultConfig(): void
    {
        $processor = new Processor();
        $config = $processor->processConfiguration(new Configuration(), [
            'progressive_image' => [
                'resolvers' => [
                    'default' => ['type' => 'filesystem', 'roots' => ['/tmp']],
                ],
            ],
        ]);

        $this->assertEquals('custom', $config['responsive_strategy']['grid']['framework']);
        $this->assertEquals(12, $config['responsive_strategy']['grid']['columns']);
        $this->assertEquals(24, $config['responsive_strategy']['grid']['gutter']);
        $this->assertEmpty($config['responsive_strategy']['grid']['layouts']);
    }

    public function testBootstrapFramework(): void
    {
        $processor = new Processor();
        $config = $processor->processConfiguration(new Configuration(), [
            'progressive_image' => [
                'resolvers' => [
                    'default' => ['type' => 'filesystem', 'roots' => ['/tmp']],
                ],
                'responsive_strategy' => [
                    'grid' => [
                        'framework' => 'bootstrap',
                    ],
                ],
            ],
        ]);

        $grid = $config['responsive_strategy']['grid'];
        $this->assertEquals('bootstrap', $grid['framework']);
        $this->assertEquals(12, $grid['columns']);
        $this->assertEquals(24, $grid['gutter']);
        $this->assertArrayHasKey('xxl', $grid['layouts']);
        $this->assertEquals(1400, $grid['layouts']['xxl']['min_viewport']);
        $this->assertEquals(1320, $grid['layouts']['xxl']['max_container']);
        $this->assertArrayHasKey('xs', $grid['layouts']);
        $this->assertEquals(0, $grid['layouts']['xs']['min_viewport']);
        $this->assertNull($grid['layouts']['xs']['max_container']);
    }

    public function testTailwindFramework(): void
    {
        $processor = new Processor();
        $config = $processor->processConfiguration(new Configuration(), [
            'progressive_image' => [
                'resolvers' => [
                    'default' => ['type' => 'filesystem', 'roots' => ['/tmp']],
                ],
                'responsive_strategy' => [
                    'grid' => [
                        'framework' => 'tailwind',
                    ],
                ],
            ],
        ]);

        $grid = $config['responsive_strategy']['grid'];
        $this->assertEquals('tailwind', $grid['framework']);
        $this->assertEquals(12, $grid['columns']);
        $this->assertEquals(0, $grid['gutter']);
        $this->assertArrayHasKey('2xl', $grid['layouts']);
        $this->assertEquals(1536, $grid['layouts']['2xl']['min_viewport']);
        $this->assertEquals(1536, $grid['layouts']['2xl']['max_container']);
    }

    public function testOverrideFrameworkDefaults(): void
    {
        $processor = new Processor();
        $config = $processor->processConfiguration(new Configuration(), [
            'progressive_image' => [
                'resolvers' => [
                    'default' => ['type' => 'filesystem', 'roots' => ['/tmp']],
                ],
                'responsive_strategy' => [
                    'grid' => [
                        'framework' => 'bootstrap',
                        'columns' => 16,
                        'layouts' => [
                            'md' => ['max_container' => 800],
                            'custom' => ['min_viewport' => 2000, 'max_container' => 1800],
                        ],
                    ],
                ],
            ],
        ]);

        $grid = $config['responsive_strategy']['grid'];
        $this->assertEquals('bootstrap', $grid['framework']);
        $this->assertEquals(16, $grid['columns']);
        $this->assertEquals(24, $grid['gutter']);

        // Overridden md
        $this->assertEquals(768, $grid['layouts']['md']['min_viewport']);
        $this->assertEquals(800, $grid['layouts']['md']['max_container']);

        // Preserved xxl
        $this->assertEquals(1400, $grid['layouts']['xxl']['min_viewport']);

        // Added custom
        $this->assertEquals(2000, $grid['layouts']['custom']['min_viewport']);
    }

    public function testVariantContextDefaults(): void
    {
        $processor = new Processor();
        $config = $processor->processConfiguration(new Configuration(), [
            'progressive_image' => [
                'resolvers' => [
                    'default' => ['type' => 'filesystem', 'roots' => ['/tmp']],
                ],
            ],
        ]);

        $this->assertNull($config['secret']);
        $this->assertNull($config['variant_store']['storage']);
        $this->assertSame('', $config['variant_store']['prefix']);
        $this->assertSame('/media/pgi', $config['variant_store']['public_url_prefix']);
        $this->assertSame(300, $config['variant_store']['fail_marker_ttl']);
        $this->assertSame('async', $config['generation']['strategy']);
        $this->assertSame('async_images', $config['generation']['transport']);
        $this->assertSame('original', $config['generation']['fallback_while_pending']);
        $this->assertNull($config['generation']['lock_store']);
        $this->assertSame('jpeg', $config['formats']['default']);
        $this->assertSame(85, $config['formats']['default_quality']);
        $this->assertSame([], $config['filter_sets']);
    }

    public function testVariantContextOverrides(): void
    {
        $processor = new Processor();
        $config = $processor->processConfiguration(new Configuration(), [
            'progressive_image' => [
                'resolvers' => [
                    'default' => ['type' => 'filesystem', 'roots' => ['/tmp']],
                ],
                'secret' => 'my-secret',
                'variant_store' => [
                    'storage' => 'oneup_flysystem.pgi_storage',
                    'public_url_prefix' => 'https://cdn.example.com/pgi',
                ],
                'generation' => [
                    'strategy' => 'sync',
                    'fallback_while_pending' => 'wait',
                ],
                'filter_sets' => [
                    'thumbnail_square' => [
                        'filters' => ['thumbnail' => ['size' => [200, 200], 'mode' => 'outbound']],
                    ],
                ],
            ],
        ]);

        $this->assertSame('my-secret', $config['secret']);
        $this->assertSame('oneup_flysystem.pgi_storage', $config['variant_store']['storage']);
        $this->assertSame('https://cdn.example.com/pgi', $config['variant_store']['public_url_prefix']);
        $this->assertSame('sync', $config['generation']['strategy']);
        $this->assertSame('wait', $config['generation']['fallback_while_pending']);
        $this->assertArrayHasKey('thumbnail_square', $config['filter_sets']);
    }
}
