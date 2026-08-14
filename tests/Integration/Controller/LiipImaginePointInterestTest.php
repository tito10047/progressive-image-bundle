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

    /**
     * Portrait 100×300 image, square 100×100 target.
     * origRatio=0.333 < targetRatio=1.0 → constrain by width, cropH=100.
     *
     * Three white pixels placed at different Y positions verify that the crop
     * window is centred on the POI, not on the image centre (Y=150).
     *
     * If the crop were centred on the image centre (startY=100), the crop
     * covers rows 100–200. A pixel at Y=25 or Y=275 would not appear in the
     * output → the test would fail.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('portraitVerticalPoiProvider')]
    public function testPortraitVerticalCentering(int $poiY, int $expectedOutY, string $label): void
    {
        $origW = 100;
        $origH = 300;
        $poiX = 50;
        $targetW = 100;
        $targetH = 100;

        $img = imagecreatetruecolor($origW, $origH);
        $black = imagecolorallocate($img, 0, 0, 0);
        $white = imagecolorallocate($img, 255, 255, 255);
        imagefill($img, 0, 0, $black);
        imagesetpixel($img, $poiX, $poiY, $white);

        $imagePath = $this->tempDir.'/portrait_poi.png';
        imagepng($img, $imagePath);
        imagedestroy($img);

        $client = $this->createLiipClient();
        $signer = $this->getUriSigner($client);
        $poi = "{$poiX}x{$poiY}";
        $url = sprintf('/progressive-image?path=%s&width=%d&height=%d&pointInterest=%s', 'portrait_poi.png', $targetW, $targetH, $poi);
        $signedUrl = $signer->sign('http://localhost'.$url);
        $client->request('GET', $signedUrl);

        $redirectUrl = $this->assertImageRedirectAndProperties($client, '/media/cache/', $targetW, $targetH);

        $container = $client->getContainer();
        $projectDir = $container->getParameter('kernel.project_dir');
        $absoluteFilePath = $projectDir.'/public'.parse_url($redirectUrl, PHP_URL_PATH);
        $resultImg = imagecreatefrompng($absoluteFilePath);

        $rgb = imagecolorat($resultImg, $poiX, $expectedOutY);
        $r = ($rgb >> 16) & 0xFF;
        $this->assertEquals(255, $r,
            "$label: pixel at output ($poiX,$expectedOutY) should be white (POI centred). "
            ."If it is black, the crop is centred on the image centre (Y=150) instead of POI (Y=$poiY)."
        );

        // The image centre row (Y=150 in orig) must NOT be the white pixel for edge POIs.
        if ($poiY !== 150) {
            $centerRow = (int) ($targetH / 2); // output row that corresponds to orig Y=150 if wrongly centred
            $rgbCenter = imagecolorat($resultImg, $poiX, $centerRow);
            $rCenter = ($rgbCenter >> 16) & 0xFF;
            $this->assertEquals(0, $rCenter,
                "$label: output centre row ($poiX,$centerRow) should be black. "
                ."If white, the crop is centred on the image centre instead of POI."
            );
        }

        imagedestroy($resultImg);
    }

    /**
     * @return array<string, array{int, int, string}>
     */
    public static function portraitVerticalPoiProvider(): array
    {
        // [poiY, expectedOutY, label]
        // cropH = 100 (same as target height, scale=1, no anti-aliasing)
        // startY = max(0, min(poiY - 50, 200))
        return [
            'POI near top (Y=25)'    => [25,  25,  'POI near top'],    // startY=0,   out=25
            'POI at centre (Y=150)'  => [150, 50,  'POI at centre'],   // startY=100, out=50
            'POI near bottom (Y=275)'=> [275, 75,  'POI near bottom'], // startY=200, out=75
        ];
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
