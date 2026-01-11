<?php

/*
 * This file is part of the Progressive Image Bundle.
 *
 * (c) Jozef Môstka <https://github.com/tito10047/progressive-image-bundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace Tito10047\ProgressiveImageBundle\Tests\Integration\Controller;

use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\UriSigner;
use Tito10047\ProgressiveImageBundle\Tests\Integration\PGIWebTestCase;
use Tito10047\ProgressiveImageBundle\UrlGenerator\LiipImagineResponsiveImageUrlGenerator;

class LiipImagineControllerTest extends AbstractLiipImagineControllerTestCase {

	public function testIndexWithFilter(): void {
		$client = $this->createLiipClient();
		$signer = $this->getUriSigner($client);

		$path   = 'test.png';
		$width  = 100;
		$height = 100;
		$filter = 'preview_big';

		$url       = sprintf('/progressive-image?path=%s&width=%d&height=%d&filter=%s', $path, $width, $height, $filter);
		$signedUrl = $signer->sign('http://localhost' . $url);

		$client->request('GET', $signedUrl);

		$this->assertImageRedirectAndProperties($client, '/media/cache/preview_big_100x100/', 100, 100);
	}

	public function testIndexWithCustomConfiguredFilter(): void {
		$client = $this->createLiipClient([
			'liip_imagine' => [
				'filter_sets' => [
					'custom_filter' => [
						'quality' => 80,
						'filters' => [
							'thumbnail' => [
								'size' => [120, 120],
								'mode' => 'outbound',
							],
						],
					],
				],
			],
		]);
		$signer = $this->getUriSigner($client);

		$path = 'test_custom.png';
		$this->fs->copy(__DIR__ . '/../../Fixtures/test_800x800.png', $this->tempDir . '/' . $path);

		$width  = 150;
		$height = 150;
		$filter = 'custom_filter';

		$url       = sprintf('/progressive-image?path=%s&width=%d&height=%d&filter=%s', $path, $width, $height, $filter);
		$signedUrl = $signer->sign('http://localhost' . $url);

		$client->request('GET', $signedUrl);

		$this->assertImageRedirectAndProperties($client, '/media/cache/custom_filter_150x150/', 150, 150);
	}
}
