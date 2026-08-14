<?php

declare(strict_types=1);

namespace Tito10047\ProgressiveImageBundle\Tests\Integration\Controller;

/**
 * Tests POI cropping with a synthetic image:
 *   - white background, 976×1734 px (same as the real fixture)
 *   - black circle (radius 30) centred at pixel (544, 594) — the POI
 *
 * The system receives POI as pixel coordinates "544x594".
 * After the crop the pixel at the output centre (180, 240) must be
 * dark (inside the circle), and a corner pixel must be white.
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

    /**
     * The crop must be centred on the POI pixel (544, 594).
     * Expected crop window: start (364, 354), size 360×480.
     * Output centre (180, 240) must be black (inside the circle).
     */
    public function testPoiCropCentresOnSubject(): void
    {
        $client = $this->createLiipClient();
        $signer = $this->getUriSigner($client);

        $poi = self::POI_PIXEL_X.'x'.self::POI_PIXEL_Y; // "544x594" — pixel coordinates

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
        $relativeFilePath = parse_url($redirectUrl, PHP_URL_PATH);
        $absoluteFilePath = $projectDir.'/public'.$relativeFilePath;

        $outImg = imagecreatefromjpeg($absoluteFilePath);
        $this->assertNotFalse($outImg, 'Could not load output JPEG');

        // Centre of output must be dark (the black circle)
        $outCentreX = (int) (self::TARGET_W / 2);
        $outCentreY = (int) (self::TARGET_H / 2);

        $rgb = imagecolorat($outImg, $outCentreX, $outCentreY);
        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;

        // Corner of output must be white (background)
        $cornerRgb = imagecolorat($outImg, 0, 0);
        $cornerR = ($cornerRgb >> 16) & 0xFF;

        imagedestroy($outImg);

        // Allow JPEG compression artefacts
        $this->assertLessThan(
            50,
            $r,
            sprintf(
                'Output centre (%d,%d) should be dark (inside the circle at POI %d,%d), got R=%d. '.
                'The crop is likely at the wrong position.',
                $outCentreX, $outCentreY,
                self::POI_PIXEL_X, self::POI_PIXEL_Y,
                $r
            )
        );
        $this->assertLessThan(50, $g, "Output centre G=$g should be dark");
        $this->assertLessThan(50, $b, "Output centre B=$b should be dark");

        $this->assertGreaterThan(
            200,
            $cornerR,
            "Output corner should be white (background), got R=$cornerR"
        );
    }
}
