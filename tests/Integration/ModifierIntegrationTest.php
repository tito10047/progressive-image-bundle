<?php

/*
 * This file is part of the Progressive Image Bundle.
 *
 * (c) Jozef Môstka <https://github.com/tito10047/progressive-image-bundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tito10047\ProgressiveImageBundle\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tito10047\ProgressiveImageBundle\DTO\BreakpointAssignment;
use Tito10047\ProgressiveImageBundle\Modifier\ModifierInterface;
use Tito10047\ProgressiveImageBundle\Modifier\ModifierProvider;
use Tito10047\ProgressiveImageBundle\Service\ResponsiveAttributeGenerator;
use Tito10047\ProgressiveImageBundle\UrlGenerator\ResponsiveImageUrlGeneratorInterface;

class TestModifier implements ModifierInterface
{
    public function supports(string $modifier): bool
    {
        return 'circle' === $modifier;
    }

    public function modify(string $modifier, array $context): array
    {
        $context['circle'] = true;

        return $context;
    }
}

class CustomFilter implements ModifierInterface
{
    public function supports(string $modifier): bool
    {
        return 'circle' === $modifier;
    }

    public function modify(string $modifier, array $context): array
    {
        $context['filter'] = 'custom_circle';

        return $context;
    }
}

/**
 * Echoes whatever context it's given straight into the query string — these tests only
 * care whether the modifier chain (ModifierProvider, tag priority) produces the right
 * context, not how any particular URL generator renders it.
 */
final class ContextEchoingUrlGenerator implements ResponsiveImageUrlGeneratorInterface
{
    public function generateUrl(string $path, int $targetW, ?int $targetH = null, ?string $pointInterest = null, array $context = []): string
    {
        return $path.'?'.http_build_query($context);
    }
}

class ModifierIntegrationTest extends TestCase
{
    private function bootKernelWithEchoingUrlGenerator(\Closure $registerModifier): ProgressiveImageTestingKernel
    {
        $kernel = new ProgressiveImageTestingKernel([
            'progressive_image' => [
                'responsive_strategy' => [
                    'generator' => 'test.url_generator',
                    'grid' => [
                        'layouts' => [
                            'md' => ['min_viewport' => 768, 'max_container' => 720],
                        ],
                        'columns' => 12,
                    ],
                    'ratios' => [
                        'landscape' => '16/9',
                    ],
                ],
            ],
        ]);

        $kernel->setCustomConfiguration(function (ContainerBuilder $container) use ($registerModifier) {
            $container->register('test.url_generator', ContextEchoingUrlGenerator::class)
                ->setPublic(true);
            $registerModifier($container);
        });

        $kernel->boot();

        return $kernel;
    }

    public function testModifiersAreRegisteredAndApplied(): void
    {
        $kernel = $this->bootKernelWithEchoingUrlGenerator(function (ContainerBuilder $container): void {
            $container->register(TestModifier::class)
                ->addTag('progressive_image.modifier');
        });
        $container = $kernel->getContainer()->get('test.service_container');

        $this->assertTrue($container->has(ModifierProvider::class) || $kernel->getContainer()->has(ModifierProvider::class));

        /** @var ResponsiveAttributeGenerator $generator */
        $generator = $container->has(ResponsiveAttributeGenerator::class)
            ? $container->get(ResponsiveAttributeGenerator::class)
            : $kernel->getContainer()->get(ResponsiveAttributeGenerator::class);

        $result = $generator->generate('test.jpg', [
            new BreakpointAssignment('md', 6, 'landscape', null, null, null, ['circle']),
        ], 1000, false);

        $srcset = $result->getSources()[0]->getSrcset();
        $this->assertStringContainsString('circle', $srcset);
        // CoreFilterModifier ties it to 'filter' => 'circle'
        $this->assertStringContainsString('filter=circle', $srcset);
    }

    public function testFilterPriority(): void
    {
        $kernel = $this->bootKernelWithEchoingUrlGenerator(function (ContainerBuilder $container): void {
            $container->register(CustomFilter::class)
                ->addTag('progressive_image.modifier'); // default priority 0 > -100
        });
        $container = $kernel->getContainer()->get('test.service_container');

        /** @var ResponsiveAttributeGenerator $generator */
        $generator = $container->has(ResponsiveAttributeGenerator::class)
            ? $container->get(ResponsiveAttributeGenerator::class)
            : $kernel->getContainer()->get(ResponsiveAttributeGenerator::class);

        $result = $generator->generate('test.jpg', [
            new BreakpointAssignment('md', 6, 'landscape', null, null, null, ['circle']),
        ], 1000, false);

        $srcset = $result->getSources()[0]->getSrcset();
        // CustomFilter should win
        $this->assertStringContainsString('filter=custom_circle', $srcset);
        $this->assertStringNotContainsString('filter=circle&', $srcset);
        $this->assertStringNotContainsString('filter=circle"', $srcset);
    }
}
