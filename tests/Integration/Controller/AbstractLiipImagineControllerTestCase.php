<?php

declare(strict_types=1);

namespace Tito10047\ProgressiveImageBundle\Tests\Integration\Controller;

use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\UriSigner;
use Tito10047\ProgressiveImageBundle\Tests\Integration\PGIWebTestCase;

abstract class AbstractLiipImagineControllerTestCase extends PGIWebTestCase {

	protected string     $tempDir;
	protected Filesystem $fs;

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
		$publicCache = __DIR__ . '/../../../public/media/cache';
		if ($this->fs->exists($publicCache)) {
			$this->fs->remove($publicCache);
		}
		parent::tearDown();
	}

	protected function createLiipClient(array $additionalConfig = []): KernelBrowser {
		if (!class_exists(CacheManager::class)) {
			$this->markTestSkipped('LiipImagineBundle is not installed.');
		}

		$config = [
			'progressive_image' => [
				'path_decorators' => [
					'progressive_image.decorator.liip_imagine',
				],
				'resolvers'       => [
					'temp' => [
						'type'  => 'filesystem',
						'roots' => [$this->tempDir],
					],
				],
				'resolver'        => 'temp',
			],
			'liip_imagine'      => [
				'loaders' => [
					'default' => [
						'filesystem' => [
							'data_root' => $this->tempDir,
						],
					],
				],
			],
		];

		return static::createClient(array_replace_recursive($config, $additionalConfig));
	}

	protected function getUriSigner(KernelBrowser $client): UriSigner {
		return $client->getContainer()->get('uri_signer');
	}

	protected function assertImageRedirectAndProperties(KernelBrowser $client, string $expectedCachePathPart, int $expectedWidth, int $expectedHeight): string {
		$this->assertResponseRedirects();
		$redirectUrl = $client->getResponse()->headers->get('Location');
		$this->assertStringContainsString($expectedCachePathPart, $redirectUrl);

		$container        = $client->getContainer();
		$projectDir       = $container->getParameter('kernel.project_dir');
		$relativeFilePath = parse_url($redirectUrl, PHP_URL_PATH);
		$absoluteFilePath = $projectDir . '/public' . $relativeFilePath;

		$this->assertFileExists($absoluteFilePath);

		$imageSize = getimagesize($absoluteFilePath);
		$this->assertEquals($expectedWidth, $imageSize[0], "Image width does not match");
		$this->assertEquals($expectedHeight, $imageSize[1], "Image height does not match");

		return $redirectUrl;
	}
}
