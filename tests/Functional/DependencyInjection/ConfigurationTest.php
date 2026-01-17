<?php

namespace Tito10047\ProgressiveImageBundle\Tests\Functional\DependencyInjection;

use Tito10047\ProgressiveImageBundle\Tests\Integration\PGITestCase;

class ConfigurationTest extends PGITestCase
{
    public function testRatiosConfiguration(): void
    {
        $this->bootKernel([
            'progressive_image' => [
                'responsive_strategy' => [
                    'ratios' => [
                        'square' => '1:1',
                    ],
                ],
            ],
        ]);

        $container = static::getContainer();
        $this->assertTrue($container->hasParameter('progressive_image.responsive_strategy.ratios'));
        $this->assertEquals(['square' => '1:1'], $container->getParameter('progressive_image.responsive_strategy.ratios'));
    }

    protected static function bootKernel(array $options = []): \Symfony\Component\HttpKernel\KernelInterface
    {
        $options = array_merge_recursive([
            'progressive_image' => [
                'resolvers' => [
                    'test' => [
                        'type' => 'filesystem',
                        'roots' => [__DIR__],
                    ],
                ],
                'resolver' => 'test',
            ],
        ], $options);

        return parent::bootKernel($options);
    }
}
