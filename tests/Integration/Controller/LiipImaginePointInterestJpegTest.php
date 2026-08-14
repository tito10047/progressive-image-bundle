<?php

declare(strict_types=1);

namespace Tito10047\ProgressiveImageBundle\Tests\Integration\Controller;

/**
 * Tests POI cropping with a synthetic image (same dimensions as the original fixture):
 *   - 976×1734 px, white background
 *   - black circle (radius 30) centred at pixel (544, 594) — the POI
 *
 * The correct algorithm:
 *   origRatio=0.563 < targetRatio=0.75  → constrain by width, crop excess height
 *   cropW=976, cropH=round(976*480/360)=1301
 *   startX=max(0,min(544-488, 0))=0, startY=max(0,min(594-650, 433))=0
 *   Crop: (0,0) size 976×1301, then scale to 360×480.
 *
 * POI in output:
 *   outPoiX = round(544 * 360/976) = 201
 *   outPoiY = round(594 * 480/1301) = 219
 *
 * Pixel at (201,219) must be dark (inside the circle).
 * Pixel at (0,0) must be white (background — top-left of the crop is also background).
 */
class LiipImaginePointInterestJpegTest extends AbstractLiipImagineControllerTestCase
{
    private const ORIG_W = 976;
    private const ORIG_H = 1734;
    private const TARGET_W = 360;
    private const TARGET_H = 480;
    private const POI_PIXEL_X = 544;
    private const POI_PIXEL_Y = 594;
    private const CIRCLE_RADIUS = 30;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fs->copy(
            __DIR__.'/../../Fixtures/otuzovanie_prve.jpeg',
            $this->tempDir.'/otuzovanie_prve.jpeg'
        );
    }

    public function testPoiCropCentresOnSubject(): void
    {
        $client = $this->createLiipClient();
        $signer = $this->getUriSigner($client);

        $poi = self::POI_PIXEL_X.'x'.self::POI_PIXEL_Y;

        $url = sprintf(
            '/progressive-image?path=%s&width=%d&height=%d&pointInterest=%s',
            'otuzovanie_prve.jpeg',
            self::TARGET_W,
            self::TARGET_H,
            $poi
        );
        $signedUrl = $signer->sign('http://localhost'.$url);

        $client->request('GET', $signedUrl);

        $redirectUrl = $this->assertImageRedirectAndProperties(
            $client,
            '/media/cache/',
            self::TARGET_W,
            self::TARGET_H
        );

        $container = $client->getContainer();
        $projectDir = $container->getParameter('kernel.project_dir');
        $absoluteFilePath = $projectDir.'/public'.parse_url($redirectUrl, PHP_URL_PATH);

        $outImg = imagecreatefromjpeg($absoluteFilePath);
        $this->assertNotFalse($outImg, 'Could not load output JPEG');

        // Calculate expected POI position in output based on the aspect-crop algorithm.
        [$outPoiX, $outPoiY] = $this->expectedOutputPoi();

        $rgb = imagecolorat($outImg, $outPoiX, $outPoiY);
        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;

        // Corner (0,0): the crop starts at (0,0) in the original — background is white.
        $cornerRgb = imagecolorat($outImg, 0, 0);
        $cornerR = ($cornerRgb >> 16) & 0xFF;

        imagedestroy($outImg);

        $this->assertLessThan(
            50, $r,
            sprintf(
                'Output pixel at POI (%d,%d) should be dark (inside the circle), got R=%d.',
                $outPoiX, $outPoiY, $r
            )
        );
        $this->assertLessThan(50, $g, "POI pixel G=$g should be dark");
        $this->assertLessThan(50, $b, "POI pixel B=$b should be dark");

        $this->assertGreaterThan(
            200, $cornerR,
            "Output corner should be white (background), got R=$cornerR"
        );
    }

    /**
     * Computes where the POI pixel appears in the output after the aspect-crop + scale.
     *
     * @return array{int, int}
     */
    private function expectedOutputPoi(): array
    {
        $targetRatio = self::TARGET_W / self::TARGET_H;
        $origRatio = self::ORIG_W / self::ORIG_H;

        if ($origRatio > $targetRatio) {
            $cropH = self::ORIG_H;
            $cropW = (int) round(self::ORIG_H * $targetRatio);
        } else {
            $cropW = self::ORIG_W;
            $cropH = (int) round(self::ORIG_W / $targetRatio);
        }

        $startX = max(0, min(self::POI_PIXEL_X - (int) ($cropW / 2), self::ORIG_W - $cropW));
        $startY = max(0, min(self::POI_PIXEL_Y - (int) ($cropH / 2), self::ORIG_H - $cropH));

        return [
            (int) round((self::POI_PIXEL_X - $startX) * self::TARGET_W / $cropW),
            (int) round((self::POI_PIXEL_Y - $startY) * self::TARGET_H / $cropH),
        ];
    }
}
