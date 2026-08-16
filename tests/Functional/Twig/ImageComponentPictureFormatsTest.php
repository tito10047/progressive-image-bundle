<?php

/*
 * This file is part of the Progressive Image Bundle.
 *
 * (c) Jozef Môstka <https://github.com/tito10047/progressive-image-bundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tito10047\ProgressiveImageBundle\Tests\Functional\Twig;

use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Filesystem\Filesystem as SymfonyFilesystem;
use Symfony\UX\TwigComponent\Test\InteractsWithTwigComponents;
use Tito10047\ProgressiveImageBundle\Tests\Integration\PGITestCase;

class ImageComponentPictureFormatsTest extends PGITestCase
{
    use InteractsWithTwigComponents;

    private string $tempDir;
    private string $storageRoot;
    private SymfonyFilesystem $fs;

    protected function setUp(): void
    {
        $this->fs = new SymfonyFilesystem();
        $this->tempDir = sys_get_temp_dir().'/progressive_image_test_picture_formats_'.uniqid();
        $this->fs->mkdir($this->tempDir);
        $this->fs->copy(__DIR__.'/../../Fixtures/test_800x800.png', $this->tempDir.'/test.png');

        $this->storageRoot = sys_get_temp_dir().'/pgi-picture-formats-test-storage-'.bin2hex(random_bytes(8));
        $this->fs->mkdir($this->storageRoot);
    }

    protected function tearDown(): void
    {
        $this->fs->remove($this->tempDir);
        $this->fs->remove($this->storageRoot);
        parent::tearDown();
    }

    public function testRenderWithPictureFormatsProducesTypedSourcesBeforeThePlainFallback(): void
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
                    'picture' => ['avif', 'webp'],
                ],
                'responsive_strategy' => [
                    'grid' => [
                        'columns' => 12,
                        'layouts' => [
                            'desktop' => [
                                'min_viewport' => 1024,
                                'max_container' => 1200,
                            ],
                            'mobile' => [
                                'min_viewport' => 0,
                                'max_container' => null,
                            ],
                        ],
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
                'sizes' => 'mobile:12 desktop:1',
            ]
        );

        $this->assertStringContainsString('<picture>', $html);

        // Within the desktop breakpoint's <source> group, avif comes before webp, which
        // comes before the plain (untyped) fallback source for that same media query —
        // <picture> matching stops at the first source whose type is supported, so the
        // most-preferred format must be listed first.
        $avifPos = strpos($html, 'type="image/avif"');
        $webpPos = strpos($html, 'type="image/webp"');
        $plainSourcePos = strpos($html, 'media="(min-width: 1024px)"');

        $this->assertIsInt($avifPos);
        $this->assertIsInt($webpPos);
        $this->assertIsInt($plainSourcePos);
        $this->assertLessThan($webpPos, $avifPos);

        // The untyped fallback <source> (no type=) for the desktop breakpoint must be the
        // last one with that media query, i.e. after both typed candidates.
        $lastUntypedSourceForDesktop = strrpos(substr($html, 0, strpos($html, '<img')), 'media="(min-width: 1024px)"');
        $this->assertGreaterThan($webpPos, $lastUntypedSourceForDesktop);

        // The <img> fallback itself never carries a type — it's the final, format-agnostic
        // catch-all.
        $imgPart = substr($html, strpos($html, '<img'));
        $this->assertStringNotContainsString('type=', $imgPart);

        // Real proof: sync generation actually wrote avif- and webp-encoded files, not just
        // the plain png.
        $this->assertNotEmpty(glob($this->storageRoot.'/*/*/*/*.avif'), 'sync generation must have written an avif variant');
        $this->assertNotEmpty(glob($this->storageRoot.'/*/*/*/*.webp'), 'sync generation must have written a webp variant');
        $this->assertNotEmpty(glob($this->storageRoot.'/*/*/*/*.png'), 'the plain/default format variant must still be generated');
    }
}
