<?php

declare(strict_types=1);

namespace Tito10047\ProgressiveImageBundle\Tests\Integration\Controller;

use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\UriSigner;
use Tito10047\ProgressiveImageBundle\Tests\Integration\PGIWebTestCase;

class LiipImagineControllerTest extends PGIWebTestCase {

	private string     $tempDir;
	private Filesystem $fs;

	protected function setUp(): void {
		$this->fs      = new Filesystem();
		$this->tempDir = sys_get_temp_dir() . '/progressive_image_test_controller_' . uniqid();
		$this->fs->mkdir($this->tempDir);
		$this->fs->copy(__DIR__ . '/../../Fixtures/test.png', $this->tempDir . '/test.png');
	}

	protected function tearDown(): void {
		if (isset($this->tempDir) && $this->fs->exists($this->tempDir)) {
			$this->fs->remove($this->tempDir);
		}
		// Also cleanup public/media/cache if it exists in project root for tests
		$publicCache = __DIR__ . '/../../../public/media/cache';
		if ($this->fs->exists($publicCache)) {
			 $this->fs->remove($publicCache); // Maybe too dangerous? Better keep it or clean subdirs
		}
		parent::tearDown();
	}

    public function testIndexWithFilter(): void
    {
		if (!class_exists(CacheManager::class)) {
			$this->markTestSkipped('LiipImagineBundle is not installed.');
		}
		$client = static::createClient([
			"progressive_image"=>[
				'path_decorators' => [
					'progressive_image.decorator.liip_imagine'
				]
			],
			"liip_imagine" => [
				"loaders"   => [
					"default" => [
						"filesystem" => [
							"data_root" => $this->tempDir
						]
					]
				]
			]
		]);

		$container = $client->getContainer();

        /** @var UriSigner $signer */
        $signer = $container->get('uri_signer');

        $path = 'test.png';
        $width = 100;
        $height = 100;
        $filter = 'preview_big';

        $url = sprintf('/progressive-image?path=%s&width=%d&height=%d&filter=%s', $path, $width, $height, $filter);
        $signedUrl = $signer->sign('http://localhost' . $url);

        $client->request('GET', $signedUrl);

        $this->assertResponseRedirects();
        $redirectUrl = $client->getResponse()->headers->get('Location');
        $this->assertStringContainsString('/media/cache/preview_big_100x100/', $redirectUrl);
        $this->assertStringNotContainsString('/rc/', $redirectUrl);

		// Verify physical file exists
		$projectDir = $container->getParameter('kernel.project_dir');
		$relativeFilePath = parse_url($redirectUrl, PHP_URL_PATH);
		$absoluteFilePath = $projectDir . '/public' . $relativeFilePath;

		$this->assertFileExists($absoluteFilePath);

		// Verify image size
		$imageSize = getimagesize($absoluteFilePath);
		$this->assertEquals(100, $imageSize[0]);
		$this->assertEquals(100, $imageSize[1]);
    }

    public function testIndexWithoutFilter(): void
    {
		if (!class_exists(\Liip\ImagineBundle\Imagine\Cache\CacheManager::class)) {
			$this->markTestSkipped('LiipImagineBundle is not installed.');
		}
        $client = static::createClient([
			"liip_imagine" => [
				"loaders"   => [
					"default" => [
						"filesystem" => [
							"data_root" => $this->tempDir
						]
					]
				]
			]
		]);
        $container = $client->getContainer();
        /** @var UriSigner $signer */
        $signer = $container->get('uri_signer');

        $path = 'test.png';
        $width = 50;
        $height = 50;

        $url = sprintf('/progressive-image?path=%s&width=%d&height=%d', $path, $width, $height);
        $signedUrl = $signer->sign('http://localhost' . $url);

        $client->request('GET', $signedUrl);

        $this->assertResponseRedirects();
        $redirectUrl = $client->getResponse()->headers->get('Location');
        $this->assertStringContainsString('/media/cache/50x50/', $redirectUrl);
        $this->assertStringNotContainsString('/rc/', $redirectUrl);

		// Verify physical file exists
		$projectDir = $container->getParameter('kernel.project_dir');
		$relativeFilePath = parse_url($redirectUrl, PHP_URL_PATH);
		$absoluteFilePath = $projectDir . '/public' . $relativeFilePath;

		$this->assertFileExists($absoluteFilePath);

		// Verify image size
		$imageSize = getimagesize($absoluteFilePath);
		$this->assertEquals(50, $imageSize[0]);
		$this->assertEquals(50, $imageSize[1]);
    }
}
