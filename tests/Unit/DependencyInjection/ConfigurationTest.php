<?php

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
                    'default' => ['type' => 'filesystem', 'roots' => ['/tmp']]
                ]
            ]
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
                    'default' => ['type' => 'filesystem', 'roots' => ['/tmp']]
                ],
                'responsive_strategy' => [
                    'grid' => [
                        'framework' => 'bootstrap'
                    ]
                ]
            ]
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
                    'default' => ['type' => 'filesystem', 'roots' => ['/tmp']]
                ],
                'responsive_strategy' => [
                    'grid' => [
                        'framework' => 'tailwind'
                    ]
                ]
            ]
        ]);

        $grid = $config['responsive_strategy']['grid'];
        $this->assertEquals('tailwind', $grid['framework']);
        $this->assertEquals(12, $grid['columns']);
        $this->assertEquals(0, $grid['gutter']);
        $this->assertArrayHasKey('2xl', $grid['layouts']);
        $this->assertEquals(1536, $grid['layouts']['2xl']['min_viewport']);
        $this->assertEquals(1536, $grid['layouts']['2xl']['max_container']);
        $this->assertArrayHasKey('default', $grid['layouts']);
        $this->assertEquals(0, $grid['layouts']['default']['min_viewport']);
        $this->assertNull($grid['layouts']['default']['max_container']);
    }

    public function testOverrideFrameworkDefaults(): void
    {
        $processor = new Processor();
        $config = $processor->processConfiguration(new Configuration(), [
            'progressive_image' => [
                'resolvers' => [
                    'default' => ['type' => 'filesystem', 'roots' => ['/tmp']]
                ],
                'responsive_strategy' => [
                    'grid' => [
                        'framework' => 'bootstrap',
                        'columns' => 16,
                        'layouts' => [
                            'md' => ['max_container' => 800],
                            'custom' => ['min_viewport' => 2000, 'max_container' => 1800]
                        ]
                    ]
                ]
            ]
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
}
