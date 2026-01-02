<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 25. 7. 2024
 * Time: 16:03
 */

namespace Tito10047\ProgressiveImageBundle\Tests\Functional\Twig;


use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\UX\TwigComponent\Test\InteractsWithTwigComponents;
use Tito10047\ProgressiveImageBundle\ProgressiveImageBundle;
use Tito10047\ProgressiveImageBundle\Tests\Integration\PGITestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Tito10047\ProgressiveImageBundle\Event\KernelResponseEventListener;
use Tito10047\ProgressiveImageBundle\Service\PreloadCollector;
use Tito10047\ProgressiveImageBundle\Twig\Components\Image;

class ImageComponentTest extends PGITestCase {

	use InteractsWithTwigComponents;

	private string     $tempDir;
	private Filesystem $fs;

	protected function setUp(): void {
		$this->fs      = new Filesystem();
		$this->tempDir = sys_get_temp_dir() . '/progressive_image_test_' . uniqid();
		$this->fs->mkdir($this->tempDir);
	}

	function testDefaultRendered() {
		self::bootKernel();

		$component = $this->mountTwigComponent(
			name: 'pgi:Image',
			data: [
				"src" => "/foo.jpg"
			]
		);

		$this->assertInstanceOf(Image::class, $component);
		$this->assertSame("/foo.jpg", $component->src);
	}

	public function testGenerateHash(): void {
		$this->_bootKernel();

		/** @var Image $component */
		$component = $this->mountTwigComponent(
			name: 'pgi:Image',
			data: [
				"src" => "/test.png"
			]
		);
		$this->assertInstanceOf(Image::class, $component);

		$this->assertNotEmpty($component->getHash());
		$this->assertGreaterThan(0, $component->getWidth());
		$this->assertGreaterThan(0, $component->getHeight());
	}

	public function testRender(): void {
		$this->bootKernel();

		$html = $this->renderTwigComponent(
			name: "pgi:Image",
			data: [
				"src" => "/test.png",
				"alt" => "test image"
			]
		);

		$stimulus = ProgressiveImageBundle::STIMULUS_CONTROLLER;

		$this->assertStringContainsString("src=\"/test.png\"", $html);
		$this->assertStringContainsString("data-controller=\"{$stimulus}\"", $html);
		$this->assertStringContainsString("data-{$stimulus}-target=\"placeholder\"", $html);
		$this->assertStringContainsString("data-{$stimulus}-target=\"highRes\"", $html);
	}

	public function testRenderMissingImage(): void {
		$this->bootKernel();

		$html = $this->renderTwigComponent(
			name: "pgi:Image",
			data: [
				"src" => "/test2.png",
				"alt" => "test image"
			]
		);

		$stimulus = ProgressiveImageBundle::STIMULUS_CONTROLLER;

		$this->assertStringContainsString("data-{$stimulus}-target=\"errorOverlay\"", $html);
	}

	public function testPreloadHeader(): void {
		if (!class_exists(CacheManager::class)) {
			$this->markTestSkipped('LiipImagineBundle is not installed.');
		}
		$cacheManager = $this->createMock(CacheManager::class);
		$cacheManager->expects($this->once())
			->method('getBrowserPath')
			->with('/test.png', 'preview_big')
			->willReturn('http://localhost/media/cache/resolve/preview_big/test.png');

		$this->_bootKernel([
			"progressive_image" => [
				'path_decorators' => ['progressive_image.decorator.liip_imagine']
			]
		]);

		self::getContainer()->set('liip_imagine.cache.manager', $cacheManager);

		$this->renderTwigComponent(
			name: "pgi:Image",
			data: [
				"src" => "/test.png",
				"preload" => true,
				"priority" => "high",
				"context" => [
					"filter" => "preview_big"
				]
			]
		);

		$preloadCollector = self::getContainer()->get(PreloadCollector::class);
		$urls = $preloadCollector->getUrls();

		$expectedUrl = 'http://localhost/media/cache/resolve/preview_big/test.png';
		$this->assertArrayHasKey($expectedUrl, $urls);

		$eventListener = self::getContainer()->get(KernelResponseEventListener::class);
		$request = new Request();
		$response = new \Symfony\Component\HttpFoundation\Response();
		$event = new ResponseEvent(self::$kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

		$eventListener($event);

		$this->assertTrue($response->headers->has('Link'));
		$this->assertStringContainsString('<' . $expectedUrl . '>; rel=preload; as=image; fetchpriority=high', $response->headers->get('Link'));
	}

	public function testRenderWithResponsiveStrategy(): void {
		if (!class_exists(CacheManager::class)) {
			$this->markTestSkipped('LiipImagineBundle is not installed.');
		}
		$cacheManager = $this->createMock(CacheManager::class);
		$cacheManager->method('getBrowserPath')
			->willReturnCallback(function($path, $filter) {
				return 'http://localhost/media/cache/resolve/' . $filter . $path;
			});

		$this->_bootKernel([
			"progressive_image" => [
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
			]
		]);

		self::getContainer()->set('liip_imagine.cache.manager', $cacheManager);

		$html = $this->renderTwigComponent(
			name: "pgi:Image",
			data: [
				"src" => "/test.png",
				"sizes" => "mobile-12 desktop-1",
			]
		);

		$this->assertStringContainsString('srcset="', $html);
		// original image is 100x100
		// mobile: 12/12 * 1920 = 1920px -> too big
		// desktop: 1/12 * 1200 = 100px -> OK
		$this->assertStringNotContainsString('1920w', $html);
		$this->assertStringContainsString('100w', $html);
		$this->assertStringContainsString('sizes="(min-width: 1024px) 100px, 100vw"', $html);
	}

	public function testPreloadHeaderWithSrcset(): void {
		if (!class_exists(CacheManager::class)) {
			$this->markTestSkipped('LiipImagineBundle is not installed.');
		}
		$cacheManager = $this->createMock(CacheManager::class);
		$cacheManager->method('getBrowserPath')
			->willReturnCallback(function($path, $filter) {
				return 'http://localhost/media/cache/resolve/' . $filter . $path;
			});

		$this->_bootKernel([
			"progressive_image" => [
				'path_decorators' => ['progressive_image.decorator.liip_imagine'],
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
				]
			]
		]);

		self::getContainer()->set('liip_imagine.cache.manager', $cacheManager);

		$this->renderTwigComponent(
			name: "pgi:Image",
			data: [
				"src" => "/test.png",
				"sizes" => "mobile-12 desktop-1",
				"preload" => true,
				"priority" => "high",
			]
		);

		$preloadCollector = self::getContainer()->get(PreloadCollector::class);
		$urls = $preloadCollector->getUrls();

		$expectedUrl = 'http://localhost/test.png';
		// LiipImagineDecorator might be used, let's check what URL we got
		$actualUrl = array_key_first($urls);

		$eventListener = self::getContainer()->get(KernelResponseEventListener::class);
		$request = new Request();
		$response = new \Symfony\Component\HttpFoundation\Response();
		$response->setContent('<html><head></head><body></body></html>');
		$event = new ResponseEvent(self::$kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

		$eventListener($event);

		$this->assertTrue($response->headers->has('Link'));
		$linkHeader = $response->headers->get('Link');
		$this->assertStringContainsString('rel=preload', $linkHeader);
		$this->assertStringContainsString('as=image', $linkHeader);
		$this->assertStringContainsString('imagesrcset="', $linkHeader);
		$this->assertStringContainsString('100w', $linkHeader);
		$this->assertStringContainsString('imagesizes="(min-width: 1024px) 100px"', $linkHeader);

		$content = $response->getContent();
		$this->assertStringContainsString('<link rel="preload"', $content);
		$this->assertStringContainsString('imagesrcset="', $content);
		$this->assertStringContainsString('imagesizes="(min-width: 1024px) 100px"', $content);
	}

	private function _bootKernel(array $extraOptions = []): void {
		$imagePath = $this->tempDir . '/test.png';
		$this->fs->copy(__DIR__ . '/../../Fixtures/test.png', $imagePath);

		$options = array_merge_recursive([
			"progressive_image" => [
				'resolvers' => [
					'test' => [
						'type'  => 'filesystem',
						'roots' => [realpath($this->tempDir)]
					]
				],
				'resolver'  => 'test'
			]
		], $extraOptions);

		self::bootKernel($options);
	}
}
