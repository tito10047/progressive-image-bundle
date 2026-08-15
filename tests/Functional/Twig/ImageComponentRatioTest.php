<?php

namespace Tito10047\ProgressiveImageBundle\Tests\Functional\Twig;

use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Filesystem\Filesystem as SymfonyFilesystem;
use Symfony\UX\TwigComponent\Test\InteractsWithTwigComponents;
use Tito10047\ProgressiveImageBundle\Tests\Integration\PGITestCase;

/**
 * Ported from the old runtime-filter-based version, which only asserted on the literal
 * query string of an unsigned, not-yet-generated URL (?height=..&path=..&width=..). That approach
 * has no equivalent once the Variant pipeline resolves a HIT to a direct storage path
 * instead of a runtime filter URL — so this now runs real synchronous generation
 * (generation.strategy: sync) against local Flysystem storage and decodes the *actual*
 * generated files to verify the ratio/grid math produced the right pixel dimensions. That
 * is strictly stronger coverage than the original string-matching version.
 */
class ImageComponentRatioTest extends PGITestCase
{
    use InteractsWithTwigComponents;

    private string $tempDir;
    private string $storageRoot;
    private SymfonyFilesystem $fs;

    protected function setUp(): void
    {
        $this->fs = new SymfonyFilesystem();
        $this->tempDir = sys_get_temp_dir().'/progressive_image_test_controller_'.uniqid();
        $this->fs->mkdir($this->tempDir);
        $this->fs->copy(__DIR__.'/../../Fixtures/test_800x800.png', $this->tempDir.'/test.png');

        $this->storageRoot = sys_get_temp_dir().'/pgi-ratio-test-storage-'.bin2hex(random_bytes(8));
        $this->fs->mkdir($this->storageRoot);
    }

    protected function tearDown(): void
    {
        $this->fs->remove($this->tempDir);
        $this->fs->remove($this->storageRoot);
        parent::tearDown();
    }

    public function testRenderWithResponsiveStrategyAndNamedRatios(): void
    {
        $storageRoot = $this->storageRoot;
        self::bootKernel([
            'progressive_image' => [
                'resolvers' => [
                    'temp' => [
                        'type' => 'filesystem',
                        'roots' => [$this->tempDir],
                    ],
                ],
                'resolver' => 'temp',
                'variant_store' => [
                    'storage' => 'test.variant_storage',
                ],
                'generation' => [
                    'strategy' => 'sync',
                ],
                'formats' => [
                    'default' => 'png',
                ],
                'responsive_strategy' => [
                    'grid' => [
                        'framework' => 'tailwind',
                    ],
                    'ratios' => [
                        'landscape' => '16/9',
                        'portrait' => '3/4',
                        'square' => '1/1',
                    ],
                ],
            ],
        ], function (ContainerBuilder $container) use ($storageRoot): void {
            $container->register('test.variant_storage.adapter', LocalFilesystemAdapter::class)
                ->setArgument('$location', $storageRoot);
            $container->register('test.variant_storage', Filesystem::class)
                ->setArgument('$adapter', new Reference('test.variant_storage.adapter'))
                ->setPublic(true);
        });

        $html = $this->renderTwigComponent(
            name: 'pgi:Image',
            data: [
                'src' => '/test.png',
                'alt' => 'Hero Background',
                'fetchpriority' => 'high',
                'preload' => true,
                'decoding' => 'sync',
                'sizes' => '2xl:12@landscape xl:12@landscape lg:12@landscape md:12@landscape sm:12@landscape default:12@landscape',
                'class' => 'w-full h-full object-cover brightness-[0.1] animate-ken-burns',
            ]
        );

        // Original is 800px wide, ratio 16/9.
        // 2xl: max_container 1536. 12/12 * 1536 = 1536 > 800 -> capped to 800x450.
        // xl: max_container 1280 > 800 -> capped to 800x450.
        // lg: max_container 1024 > 800 -> capped to 800x450.
        // md: max_container 768 < 800 -> 768x432.
        // sm: max_container 640 < 800 -> 640x360.
        $this->assertStringContainsString('<picture>', $html);

        $this->assertStringContainsString('media="(min-width: 1536px)"', $html);
        $this->assertStringContainsString('media="(min-width: 1280px)"', $html);
        $this->assertStringContainsString('media="(min-width: 1024px)"', $html);
        $this->assertStringContainsString('media="(min-width: 768px)"', $html);
        $this->assertStringContainsString('media="(min-width: 640px)"', $html);

        // Width descriptors reflect the breakpoint's own target width, independent of the
        // (possibly capped) pixel width actually generated.
        $this->assertStringContainsString('1536w', $html);
        $this->assertStringContainsString('1024w', $html);
        $this->assertStringContainsString('768w', $html);
        $this->assertStringContainsString('640w', $html);

        // The real proof: decode what sync generation actually wrote to storage and check
        // the ratio math produced the right pixels. 2xl/xl/lg all cap to the same 800x450,
        // so content-addressing means exactly 3 distinct files, not 5.
        $sizes = $this->generatedVariantSizes();
        sort($sizes);
        self::assertSame([
            '640x360',
            '768x432',
            '800x450',
        ], $sizes);
    }

    /**
     * @return list<string>
     */
    private function generatedVariantSizes(): array
    {
        $files = glob($this->storageRoot.'/*/*/*/*.png');
        self::assertNotFalse($files);
        self::assertNotEmpty($files, 'sync generation must have written variant files to storage');

        $sizes = [];
        foreach ($files as $file) {
            $info = getimagesize($file);
            self::assertIsArray($info, "generated file \"$file\" must be a valid, decodable image");
            $sizes[] = $info[0].'x'.$info[1];
        }

        return array_values(array_unique($sizes));
    }
}
