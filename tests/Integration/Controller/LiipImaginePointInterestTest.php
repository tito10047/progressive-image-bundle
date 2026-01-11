<?php

declare(strict_types=1);

namespace Tito10047\ProgressiveImageBundle\Tests\Integration\Controller;

class LiipImaginePointInterestTest extends AbstractLiipImagineControllerTestCase {

	public function testIndexWithPointInterest(): void {
		$client = $this->createLiipClient();
		$signer = $this->getUriSigner($client);

		$path   = 'test.png';
		$width  = 50;
		$height = 50;
		$poi    = '0x0'; // Upper left corner

		$url       = sprintf('/progressive-image?path=%s&width=%d&height=%d&pointInterest=%s', $path, $width, $height, $poi);
		$signedUrl = $signer->sign('http://localhost' . $url);

		$client->request('GET', $signedUrl);

		$this->assertImageRedirectAndProperties($client, '/media/cache/50x50_0x0/', 50, 50);
	}

	public function testPointInterestCropping(): void {
		// 1. Create a black 100x100 image with one white pixel at 75, 25
		$origW        = 100;
		$origH        = 100;
		$poiX_percent = 75;
		$poiY_percent = 25;
		$pixelX       = (int) ($poiX_percent / 100 * $origW); // 75
		$pixelY       = (int) ($poiY_percent / 100 * $origH); // 25

		$img   = imagecreatetruecolor($origW, $origH);
		$black = imagecolorallocate($img, 0, 0, 0);
		$white = imagecolorallocate($img, 255, 255, 255);
		imagefill($img, 0, 0, $black);
		imagesetpixel($img, $pixelX, $pixelY, $white);

		$imagePath = $this->tempDir . '/poi_test.png';
		imagepng($img, $imagePath);
		imagedestroy($img);

		$client = $this->createLiipClient();
		$signer = $this->getUriSigner($client);

		$targetW = 50;
		$targetH = 50;
		$poi     = "{$poiX_percent}x{$poiY_percent}"; // "75x25"

		$url       = sprintf('/progressive-image?path=%s&width=%d&height=%d&pointInterest=%s', 'poi_test.png', $targetW, $targetH, $poi);
		$signedUrl = $signer->sign('http://localhost' . $url);

		$client->request('GET', $signedUrl);

		$redirectUrl = $this->assertImageRedirectAndProperties($client, '/media/cache/50x50_75x25/', 50, 50);

		$container        = $client->getContainer();
		$projectDir       = $container->getParameter('kernel.project_dir');
		$relativeFilePath = parse_url($redirectUrl, PHP_URL_PATH);
		$absoluteFilePath = $projectDir . '/public' . $relativeFilePath;

		// 2. Check if the white pixel is in the center of the resulting 50x50 image
		// Center of 50x50 is 25, 25
		$resultImg = imagecreatefrompng($absoluteFilePath);
		$rgb       = imagecolorat($resultImg, 25, 25);
		$r         = ($rgb >> 16) & 0xFF;
		$g         = ($rgb >> 8) & 0xFF;
		$b         = $rgb & 0xFF;

		$this->assertEquals(255, $r, 'Middle pixel should be white (R)');
		$this->assertEquals(255, $g, 'Middle pixel should be white (G)');
		$this->assertEquals(255, $b, 'Middle pixel should be white (B)');

		// Check some other pixel if it is black
		$rgbBlack = imagecolorat($resultImg, 0, 0);
		$this->assertEquals(0, $rgbBlack & 0xFF, 'Corner pixel should be black');

		imagedestroy($resultImg);
	}
}
