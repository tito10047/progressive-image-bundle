<?php

declare(strict_types=1);

namespace Tito10047\ProgressiveImageBundle\Tests\Unit\UrlGenerator;

use Liip\ImagineBundle\Exception\Imagine\Filter\NonExistingFilterException;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Liip\ImagineBundle\Imagine\Filter\FilterConfiguration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Tito10047\ProgressiveImageBundle\Service\LiipImagineRuntimeConfigGeneratorInterface;
use Tito10047\ProgressiveImageBundle\UrlGenerator\LiipImagineResponsiveImageUrlGenerator;

class LiipImagineResponsiveImageUrlGeneratorTest extends TestCase
{
	private CacheManager $cacheManager;
	private UrlGeneratorInterface $router;
	private UriSigner $uriSigner;
	private LiipImagineRuntimeConfigGeneratorInterface $runtimeConfigGenerator;
	private FilterConfiguration $filterConfiguration;
	private TagAwareCacheInterface $cache;
	private LiipImagineResponsiveImageUrlGenerator $generator;

	protected function setUp(): void
	{
		if (!class_exists(CacheManager::class)) {
			$this->markTestSkipped('LiipImagineBundle is not installed.');
		}
		$this->cacheManager = $this->createMock(CacheManager::class);
		$this->router = $this->createMock(UrlGeneratorInterface::class);
		$this->uriSigner = $this->createMock(UriSigner::class);
		$this->runtimeConfigGenerator = $this->createMock(LiipImagineRuntimeConfigGeneratorInterface::class);
		$this->filterConfiguration = $this->createMock(FilterConfiguration::class);
		$this->cache = $this->createMock(TagAwareCacheInterface::class);

		$this->generator = new LiipImagineResponsiveImageUrlGenerator(
			$this->cacheManager,
			$this->router,
			$this->uriSigner,
			$this->runtimeConfigGenerator,
			$this->filterConfiguration,
			$this->cache,
			'my_filter'
		);
	}

	public function testGenerateUrlWhenCached(): void
	{
		$path = 'test.jpg';
		$targetW = 100;
		$targetH = 100;

		$this->runtimeConfigGenerator->expects($this->once())
			->method('generate')
			->with($targetW, $targetH, 'my_filter')
			->willReturn(['filterName' => 'my_filter_100x100', 'config' => []]);

		$this->cacheManager->expects($this->once())
			->method('isStored')
			->with($path, 'my_filter_100x100')
			->willReturn(true);

		$this->cacheManager->expects($this->once())
			->method('getBrowserPath')
			->with($path, 'my_filter_100x100')
			->willReturn('/media/cache/my_filter_100x100/test.jpg');

		$this->router->expects($this->never())->method('generate');

		$url = $this->generator->generateUrl($path, $targetW, $targetH);

		$this->assertEquals('/media/cache/my_filter_100x100/test.jpg', $url);
	}

	public function testGenerateUrlWhenNotCached(): void
	{
		$path = 'test.jpg';
		$targetW = 100;
		$targetH = 100;

		$this->runtimeConfigGenerator->expects($this->once())
			->method('generate')
			->with($targetW, $targetH, 'my_filter')
			->willReturn(['filterName' => 'my_filter_100x100', 'config' => ['foo' => 'bar']]);

		$this->cacheManager->expects($this->once())
			->method('isStored')
			->with($path, 'my_filter_100x100')
			->willReturn(false);

		$this->cache->expects($this->once())
			->method('invalidateTags')
			->with(['pgi_tag_' . md5($path)]);

		$this->filterConfiguration->expects($this->once())
			->method('get')
			->with('my_filter_100x100')
			->willThrowException(new NonExistingFilterException());

		$this->filterConfiguration->expects($this->once())
			->method('set')
			->with('my_filter_100x100', ['foo' => 'bar']);

		$this->router->expects($this->once())
			->method('generate')
			->with('progressive_image_filter', [
				'path' => $path,
				'width' => $targetW,
				'height' => $targetH,
				'filter' => 'my_filter',
				'pointInterest' => null,
			], UrlGeneratorInterface::ABSOLUTE_URL)
			->willReturn('http://localhost/progressive-image?path=test.jpg&width=100&height=100&filter=my_filter');

		$this->uriSigner->expects($this->once())
			->method('sign')
			->with('http://localhost/progressive-image?path=test.jpg&width=100&height=100&filter=my_filter')
			->willReturn('http://localhost/progressive-image?path=test.jpg&width=100&height=100&filter=my_filter&_hash=123');

		$url = $this->generator->generateUrl($path, $targetW, $targetH);

		$this->assertEquals('http://localhost/progressive-image?path=test.jpg&width=100&height=100&filter=my_filter&_hash=123', $url);
	}
}
