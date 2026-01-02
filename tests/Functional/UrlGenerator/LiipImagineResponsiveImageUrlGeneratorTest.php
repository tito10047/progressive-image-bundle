<?php

declare(strict_types=1);

namespace Tito10047\ProgressiveImageBundle\Tests\Functional\UrlGenerator;

use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Symfony\Component\Filesystem\Filesystem;
use Tito10047\ProgressiveImageBundle\Tests\Integration\PGIWebTestCase;
use Tito10047\ProgressiveImageBundle\UrlGenerator\LiipImagineResponsiveImageUrlGenerator;

class LiipImagineResponsiveImageUrlGeneratorTest extends PGIWebTestCase
{
    private string $tempDir;
    private Filesystem $fs;

    protected function setUp(): void
    {
        $this->fs = new Filesystem();
        $this->tempDir = sys_get_temp_dir() . '/progressive_image_test_generator_' . uniqid();
        $this->fs->mkdir($this->tempDir);
        $this->fs->copy(__DIR__ . '/../../Fixtures/test.png', $this->tempDir . '/test.png');
    }

    protected function tearDown(): void
    {
        if (isset($this->tempDir) && $this->fs->exists($this->tempDir)) {
            $this->fs->remove($this->tempDir);
        }
        $publicCache = __DIR__ . '/../../../public/media/cache';
        if ($this->fs->exists($publicCache)) {
            $this->fs->remove($publicCache);
        }
        parent::tearDown();
    }

    public function testGenerateUrlWhenNotInCache(): void
    {
        if (!class_exists(CacheManager::class)) {
            $this->markTestSkipped('LiipImagineBundle is not installed.');
        }

        $client = static::createClient([
            "liip_imagine" => [
                "loaders" => [
                    "default" => [
                        "filesystem" => [
                            "data_root" => $this->tempDir
                        ]
                    ]
                ]
            ]
        ]);

        $container = $client->getContainer();
        /** @var LiipImagineResponsiveImageUrlGenerator $generator */
        $generator = $container->get(LiipImagineResponsiveImageUrlGenerator::class);

        $path = 'test.png';
        $width = 150;
        $height = 150;

        $url = $generator->generateUrl($path, $width, $height);

        // Should return a signed controller URL
        $this->assertStringContainsString('/progressive-image', $url);
        $this->assertStringContainsString('path=test.png', $url);
        $this->assertStringContainsString('width=150', $url);
        $this->assertStringContainsString('height=150', $url);
        $this->assertStringContainsString('_hash=', $url);

        // Verify that the generated URL actually works and redirects to the image
        $client->request('GET', $url);
        $this->assertResponseRedirects();
        
        $redirectUrl = $client->getResponse()->headers->get('Location');
        $this->assertStringContainsString('/media/cache/150x150/', $redirectUrl);
        $this->assertStringNotContainsString('resolve', $redirectUrl);

        $projectDir = $container->getParameter('kernel.project_dir');
        $relativeFilePath = parse_url($redirectUrl, PHP_URL_PATH);
        $absoluteFilePath = $projectDir . '/public' . $relativeFilePath;

        $this->assertFileExists($absoluteFilePath);
    }

    public function testGenerateUrlWhenInCache(): void
    {
        if (!class_exists(CacheManager::class)) {
            $this->markTestSkipped('LiipImagineBundle is not installed.');
        }

        $client = static::createClient([
            "liip_imagine" => [
                "loaders" => [
                    "default" => [
                        "filesystem" => [
                            "data_root" => $this->tempDir
                        ]
                    ]
                ]
            ]
        ]);

        $container = $client->getContainer();
        /** @var LiipImagineResponsiveImageUrlGenerator $generator */
        $generator = $container->get(LiipImagineResponsiveImageUrlGenerator::class);
        /** @var CacheManager $cacheManager */
        $cacheManager = $container->get('liip_imagine.cache.manager');
        /** @var \Symfony\Component\HttpFoundation\UriSigner $signer */
        $signer = $container->get('uri_signer');

        $path = 'test.png';
        $width = 200;
        $height = 200;
        $filterName = '200x200';

        // Pre-generate using the controller (similar to LiipImagineControllerTest)
        $url = sprintf('/progressive-image?path=%s&width=%d&height=%d', $path, $width, $height);
        $signedUrl = $signer->sign('http://localhost' . $url);

        $client->request('GET', $signedUrl);
        $this->assertResponseRedirects();
        
        $redirectUrl = $client->getResponse()->headers->get('Location');
        $this->assertStringContainsString('/media/cache/200x200/', $redirectUrl);
        $this->assertStringNotContainsString('resolve', $redirectUrl);

        // Verify it is now stored
        $this->assertTrue($cacheManager->isStored($path, $filterName), "Image should be stored in cache after controller request");

        // Now call generateUrl - it should return the direct path to the cached image
        $cachedUrl = $generator->generateUrl($path, $width, $height);

        $this->assertStringNotContainsString('/progressive-image', $cachedUrl);
        $this->assertStringContainsString('/media/cache/200x200/', $cachedUrl);
        
        // Ensure the file exists on disk
        $projectDir = $container->getParameter('kernel.project_dir');
        $relativeFilePath = parse_url($cachedUrl, PHP_URL_PATH);
        $absoluteFilePath = $projectDir . '/public' . $relativeFilePath;
        $this->assertFileExists($absoluteFilePath);
    }
}
