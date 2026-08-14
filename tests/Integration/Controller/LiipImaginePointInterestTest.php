<?php

declare(strict_types=1);

namespace Tito10047\ProgressiveImageBundle\Tests\Integration\Controller;

class LiipImaginePointInterestTest extends AbstractLiipImagineControllerTestCase
{
    public function testIndexWithPointInterest(): void
    {
        $client = $this->createLiipClient();
        $signer = $this->getUriSigner($client);

        $path = 'test.png';
        $width = 50;
        $height = 50;
        $poi = '0x0'; // Upper left corner

        $url = sprintf('/progressive-image?path=%s&width=%d&height=%d&pointInterest=%s', $path, $width, $height, $poi);
        $signedUrl = $signer->sign('http://localhost'.$url);

        $client->request('GET', $signedUrl);

        $this->assertImageRedirectAndProperties($client, '/media/cache/50x50_0x0/', 50, 50);
    }

    public function testPointInterestCropping(): void
    {
        // Landscape 200x100 image, black background, white pixel at (150, 50).
        // POI at pixel (150, 50), target 100x100 (square).
        //
        // origRatio=2.0 > targetRatio=1.0 → constrain by height, crop width.
        // cropH=100, cropW=100; startX=max(0,min(100,100))=100, startY=0.
        // Crop: (100,0) size 100x100 → thumbnail inset 100x100 (scale factor=1, no-op).
        // White pixel maps to crop-space (150-100, 50-0)=(50,50) → output (50,50). ✓
        $origW = 200;
        $origH = 100;
        $pixelX = 150;
        $pixelY = 50;
        $targetW = 100;
        $targetH = 100;

        $img = imagecreatetruecolor($origW, $origH);
        $black = imagecolorallocate($img, 0, 0, 0);
        $white = imagecolorallocate($img, 255, 255, 255);
        imagefill($img, 0, 0, $black);
        imagesetpixel($img, $pixelX, $pixelY, $white);

        $imagePath = $this->tempDir.'/poi_test.png';
        imagepng($img, $imagePath);
        imagedestroy($img);

        $client = $this->createLiipClient();
        $signer = $this->getUriSigner($client);

        $poi = "{$pixelX}x{$pixelY}"; // "150x50" — pixel coordinates

        $url = sprintf('/progressive-image?path=%s&width=%d&height=%d&pointInterest=%s', 'poi_test.png', $targetW, $targetH, $poi);
        $signedUrl = $signer->sign('http://localhost'.$url);

        $client->request('GET', $signedUrl);

        $redirectUrl = $this->assertImageRedirectAndProperties($client, '/media/cache/100x100_150x50/', $targetW, $targetH);

        $container = $client->getContainer();
        $projectDir = $container->getParameter('kernel.project_dir');
        $relativeFilePath = parse_url($redirectUrl, PHP_URL_PATH);
        $absoluteFilePath = $projectDir.'/public'.$relativeFilePath;

        $resultImg = imagecreatefrompng($absoluteFilePath);

        // White pixel should be at (50, 50) of the 100x100 output (no scaling, exact pixel).
        $rgb = imagecolorat($resultImg, 50, 50);
        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;

        $this->assertEquals(255, $r, 'Pixel (50,50) should be white (R)');
        $this->assertEquals(255, $g, 'Pixel (50,50) should be white (G)');
        $this->assertEquals(255, $b, 'Pixel (50,50) should be white (B)');

        // Corner (0,0) is from the left half of the original — should be black background.
        $rgbCorner = imagecolorat($resultImg, 0, 0);
        $this->assertEquals(0, $rgbCorner & 0xFF, 'Corner pixel should be black');

        imagedestroy($resultImg);
    }
}
