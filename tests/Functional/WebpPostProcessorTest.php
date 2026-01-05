<?php

declare(strict_types=1);

namespace Tito10047\ProgressiveImageBundle\Tests\Functional;

use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Tito10047\ProgressiveImageBundle\Tests\Integration\PGIWebTestCase;
use Tito10047\ProgressiveImageBundle\UrlGenerator\ResponsiveImageUrlGeneratorInterface;

class WebpPostProcessorTest extends PGIWebTestCase {

	private string     $tempDir;
	private Filesystem $fs;

	protected function setUp(): void {
		$this->fs      = new Filesystem();
		$this->tempDir = sys_get_temp_dir() . '/progressive_image_test_issue_' . uniqid();
		$this->fs->mkdir($this->tempDir);
		$this->fs->copy(__DIR__ . '/../Fixtures/test.png', $this->tempDir . '/test.png');

		$publicCache = __DIR__ . '/../../public/media/cache';
		if ($this->fs->exists($publicCache)) {
			$this->fs->remove($publicCache);
		}
	}

	protected function tearDown(): void {
		if (isset($this->tempDir) && $this->fs->exists($this->tempDir)) {
			$this->fs->remove($this->tempDir);
		}
		$publicCache = __DIR__ . '/../../public/media/cache';
		if ($this->fs->exists($publicCache)) {
			$this->fs->remove($publicCache);
		}
		parent::tearDown();
	}

	public function testCwebpPostProcessorCreatesDuplicateExtensions(): void {
		if (!class_exists(CacheManager::class)) {
			$this->markTestSkipped('LiipImagineBundle is not installed.');
		}

		$client = static::createClient([
			'progressive_image' => [
				'image_configs' => [
					'quality'         => 75,
					'post_processors' => [
						'cwebp' => [
							'q'        => 63,
							'm'        => 6,
							'metadata' => null,
						],
					],
				],
				'resolvers'     => [
					'temp' => [
						'type'  => 'filesystem',
						'roots' => [$this->tempDir],
					],
				],
				'resolver'      => 'temp',
			],
			'liip_imagine'      => [
				'loaders'                     => [
					'default' => [
						'filesystem' => [
							'data_root' => $this->tempDir,
						],
					],
				],
				'default_filter_set_settings' => [
					'format' => 'webp',
				],
				'webp'                        => [
					'generate' => true
				]
			],
		]);

		$container = $client->getContainer();
		/** @var ResponsiveImageUrlGeneratorInterface $urlGenerator */
		$urlGenerator = $container->get(ResponsiveImageUrlGeneratorInterface::class);

		$path   = 'test.png';
		$width  = 100;
		$height = 100;
		$this->fs->copy(__DIR__ . '/../Fixtures/test.png', $this->tempDir . '/test.png');

		// 1. Generate URL (signed URL for controller)
		$signedUrl = $urlGenerator->generateUrl($path, $width, $height);

		// 2. Call the controller to trigger image generation
		$client->request('GET', $signedUrl, [], [], ['HTTP_ACCEPT' => 'image/webp,image/apng,image/*,*/*;q=0.8']);
		$client->getResponse();

		$this->assertResponseRedirects();

		// 3. Check the file system
		$projectDir = $container->getParameter('kernel.project_dir');


		$files = $this->recursiveListFiles($projectDir . '/public/media/cache');

		$webpWebpFound = false;
		foreach ($files as $file) {
			if (str_ends_with($file, '.webp')) {
				$webpWebpFound = true;
			}
		}

		$this->assertTrue($webpWebpFound, 'Súbor s príponou .webp.webp by mal byť nájdený (reprodukcia chyby)');

		// 4. Check what the URL generator returns now that the file is "stored"
		$request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => 'image/webp']);
		$container->get('request_stack')->push($request);

		$urlAfterGeneration = $urlGenerator->generateUrl($path, $width, $height);

		// User hovorí: "vráti mi cestu k tomu vacsiamu siboru question_hero.webp"
		$this->assertStringEndsWith('test.png.webp', $urlAfterGeneration, 'URL musi vratit url adresu webp obrazka ktory vygenerovalo');
	}

	private function recursiveListFiles(string $dir): array {
		$results = [];
		if (!is_dir($dir)) {
			return $results;
		}
		$files = scandir($dir);
		foreach ($files as $file) {
			if ($file === '.' || $file === '..') {
				continue;
			}
			$path = $dir . '/' . $file;
			if (is_dir($path)) {
				$results = array_merge($results, $this->recursiveListFiles($path));
			} else {
				$results[] = $path;
			}
		}
		return $results;
	}
}
